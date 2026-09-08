import test from 'node:test';
import assert from 'node:assert/strict';
import * as Y from 'yjs';
import { SynkkYjsProvider } from '../../resources/js/collaboration/yjs-provider.js';
import {
  encodeEncryptedUpdate,
  decodeEncryptedUpdate,
  uint8ToHex,
  hexToUint8
} from '../../resources/js/collaboration/encrypted-update-codec.js';

async function createTestAesKey() {
  return await crypto.subtle.generateKey(
    { name: 'AES-GCM', length: 256 },
    true,
    ['encrypt', 'decrypt']
  );
}

test('two independent Y.Doc instances converge deterministically', () => {
  const alice = new Y.Doc();
  const bob = new Y.Doc();

  alice.getText('markdown').insert(0, 'Alpha ');
  bob.getText('markdown').insert(0, 'Beta ');

  const aliceUpdate = Y.encodeStateAsUpdate(alice);
  const bobUpdate = Y.encodeStateAsUpdate(bob);

  Y.applyUpdate(alice, bobUpdate);
  Y.applyUpdate(bob, aliceUpdate);

  assert.equal(alice.getText('markdown').toString(), bob.getText('markdown').toString());
});

test('reverse delivery of updates converges to the same state', () => {
  const docA = new Y.Doc();
  const textA = docA.getText('markdown');

  textA.insert(0, 'First ');
  const update1 = Y.encodeStateAsUpdate(docA);

  textA.insert(6, 'Second ');
  const update2 = Y.encodeStateAsUpdate(docA);

  textA.insert(13, 'Third');
  const update3 = Y.encodeStateAsUpdate(docA);

  // Apply in order (1, 2, 3) on docNormal
  const docNormal = new Y.Doc();
  Y.applyUpdate(docNormal, update1);
  Y.applyUpdate(docNormal, update2);
  Y.applyUpdate(docNormal, update3);

  // Apply in reverse order (3, 2, 1) on docReverse
  const docReverse = new Y.Doc();
  Y.applyUpdate(docReverse, update3);
  Y.applyUpdate(docReverse, update2);
  Y.applyUpdate(docReverse, update1);

  assert.equal(docNormal.getText('markdown').toString(), docReverse.getText('markdown').toString());
});

test('duplicate update delivery is idempotent', () => {
  const doc1 = new Y.Doc();
  const doc2 = new Y.Doc();

  doc1.getText('markdown').insert(0, 'Hello World');
  const update = Y.encodeStateAsUpdate(doc1);

  // Apply once
  Y.applyUpdate(doc2, update);
  const textOnce = doc2.getText('markdown').toString();

  // Apply duplicate three times
  Y.applyUpdate(doc2, update);
  Y.applyUpdate(doc2, update);
  Y.applyUpdate(doc2, update);

  assert.equal(doc2.getText('markdown').toString(), textOnce);
});

test('provider queues offline local updates and flushes on connect', async () => {
  const doc = new Y.Doc();
  const sentEnvelopes = [];

  const transport = {
    sendUpdate: async (env) => {
      sentEnvelopes.push(env);
      return { acknowledged: true, sequence: sentEnvelopes.length };
    },
    fetchUpdates: async (since) => {
      return { document_id: 42, updates: [] };
    }
  };

  const provider = new SynkkYjsProvider({
    doc,
    vaultId: 1,
    documentId: 42,
    path: 'Test.md',
    transport
  });

  // Edit while offline
  doc.getText('markdown').insert(0, 'Offline Edit 1');
  assert.equal(provider.outbox.length, 1);
  assert.equal(sentEnvelopes.length, 0);

  doc.getText('markdown').insert(14, ' and 2');
  assert.equal(provider.outbox.length, 2);
  assert.equal(sentEnvelopes.length, 0);

  // Connect
  await provider.connect();

  assert.equal(provider.connected, true);
  assert.equal(sentEnvelopes.length, 2);
  assert.equal(provider.outbox.length, 0);
  assert.equal(provider.latestSequence, 2);

  provider.destroy();
});

test('acknowledgement clears outbox and advances server sequence', () => {
  const doc = new Y.Doc();
  const provider = new SynkkYjsProvider({
    doc,
    vaultId: 1,
    documentId: 10,
    path: 'AckTest.md'
  });

  provider.outbox.push({
    clientUpdateId: 'client-1',
    status: 'pending'
  });
  provider.outbox.push({
    clientUpdateId: 'client-2',
    status: 'pending'
  });

  assert.equal(provider.outbox.length, 2);

  provider.acknowledge('client-1', 5);
  assert.equal(provider.outbox.length, 1);
  assert.equal(provider.latestSequence, 5);

  provider.acknowledge('client-2', 7);
  assert.equal(provider.outbox.length, 0);
  assert.equal(provider.latestSequence, 7);

  provider.destroy();
});

test('reconnect catch-up applies missing remote updates', async () => {
  const doc = new Y.Doc();
  const remoteDoc = new Y.Doc();
  remoteDoc.getText('markdown').insert(0, 'Remote State');

  const remoteUpdateBase64 = Buffer.from(Y.encodeStateAsUpdate(remoteDoc)).toString('base64');

  const transport = {
    fetchUpdates: async (since) => {
      return {
        document_id: 99,
        updates: [
          {
            sequence: 1,
            payload: remoteUpdateBase64,
            is_encrypted: false,
          }
        ]
      };
    }
  };

  const provider = new SynkkYjsProvider({
    doc,
    vaultId: 1,
    documentId: 99,
    path: 'CatchUp.md',
    transport
  });

  await provider.connect();

  assert.equal(provider.synced, true);
  assert.equal(doc.getText('markdown').toString(), 'Remote State');
  assert.equal(provider.latestSequence, 1);

  provider.destroy();
});

test('encrypted payload convergence across two E2EE providers', async () => {
  const key = await createTestAesKey();

  const aliceDoc = new Y.Doc();
  const bobDoc = new Y.Doc();

  const serverUpdates = [];

  const aliceTransport = {
    sendUpdate: async (env) => {
      const seq = serverUpdates.length + 1;
      const record = { ...env, sequence: seq };
      serverUpdates.push(record);
      return { acknowledged: true, sequence: seq };
    }
  };

  const bobTransport = {
    sendUpdate: async (env) => {
      const seq = serverUpdates.length + 1;
      const record = { ...env, sequence: seq };
      serverUpdates.push(record);
      return { acknowledged: true, sequence: seq };
    }
  };

  const aliceProvider = new SynkkYjsProvider({
    doc: aliceDoc,
    vaultId: 100,
    documentId: 200,
    cryptoKey: key,
    transport: aliceTransport
  });

  const bobProvider = new SynkkYjsProvider({
    doc: bobDoc,
    vaultId: 100,
    documentId: 200,
    cryptoKey: key,
    transport: bobTransport
  });

  await aliceProvider.connect();
  await bobProvider.connect();

  // Alice inserts text
  aliceDoc.getText('markdown').insert(0, 'Secret Alice ');
  // Bob inserts text
  bobDoc.getText('markdown').insert(0, 'Secret Bob ');

  await aliceProvider.waitForPending();
  await bobProvider.waitForPending();

  // Both transmitted encrypted envelopes to serverUpdates
  assert.equal(serverUpdates.length, 2);
  assert.equal(serverUpdates[0].encrypted, true);
  assert.equal(serverUpdates[1].encrypted, true);

  // Both catch up on the server journal
  await bobProvider.applyCatchUp(serverUpdates);
  await aliceProvider.applyCatchUp(serverUpdates);

  assert.equal(aliceDoc.getText('markdown').toString(), bobDoc.getText('markdown').toString());
  assert.ok(aliceDoc.getText('markdown').toString().includes('Secret Alice'));
  assert.ok(aliceDoc.getText('markdown').toString().includes('Secret Bob'));

  aliceProvider.destroy();
  bobProvider.destroy();
});

test('wrong key refusal locks document and leaves sequence unacknowledged', async () => {
  const correctKey = await createTestAesKey();
  const wrongKey = await createTestAesKey();

  const docAlice = new Y.Doc();
  docAlice.getText('markdown').insert(0, 'Encrypted Content');
  const rawUpdate = Y.encodeStateAsUpdate(docAlice);

  const encoded = await encodeEncryptedUpdate(rawUpdate, correctKey, {
    vaultId: 1,
    documentId: 5,
    purpose: 'yjs-update',
    formatVersion: 2
  });

  const docBob = new Y.Doc();
  const bobProvider = new SynkkYjsProvider({
    doc: docBob,
    vaultId: 1,
    documentId: 5,
    cryptoKey: wrongKey
  });

  await assert.rejects(async () => {
    await bobProvider.applyCatchUp([
      {
        sequence: 1,
        payload: encoded.ciphertextBase64,
        encryption_iv: encoded.ivHex,
        encryption_tag: encoded.tagHex,
        is_encrypted: true,
        format_version: 2
      }
    ]);
  }, /authentication failed/);

  // Provider must be locked and sequence unacknowledged
  assert.equal(bobProvider.locked, true);
  assert.equal(bobProvider.latestSequence, 0);

  // Further edits on locked document must be refused
  await assert.rejects(async () => {
    await bobProvider.queueLocalUpdate(new Uint8Array([1, 2, 3]));
  }, /locked/);

  bobProvider.destroy();
});

test('tampered AAD document ID mismatch fails authentication', async () => {
  const key = await createTestAesKey();

  const doc = new Y.Doc();
  doc.getText('markdown').insert(0, 'Doc 1 Content');
  const rawUpdate = Y.encodeStateAsUpdate(doc);

  // Encrypted specifically for documentId: 1
  const encoded = await encodeEncryptedUpdate(rawUpdate, key, {
    vaultId: 10,
    documentId: 1,
    purpose: 'yjs-update',
    formatVersion: 2
  });

  // Attempt to apply to a provider bound to documentId: 2 (e.g. cross-doc replay attack)
  const targetDoc = new Y.Doc();
  const targetProvider = new SynkkYjsProvider({
    doc: targetDoc,
    vaultId: 10,
    documentId: 2,
    cryptoKey: key
  });

  await assert.rejects(async () => {
    await targetProvider.applyCatchUp([
      {
        sequence: 1,
        payload: encoded.ciphertextBase64,
        encryption_iv: encoded.ivHex,
        encryption_tag: encoded.tagHex,
        is_encrypted: true,
        format_version: 2
      }
    ]);
  }, /authentication failed/);

  assert.equal(targetProvider.locked, true);
  targetProvider.destroy();
});

test('subscribes to Echo private channel and processes live committed updates', async () => {
  const doc = new Y.Doc();
  let subscribedChannel = null;
  let eventCallback = null;
  let leftChannel = null;

  const fakeEcho = {
    private: (name) => {
      subscribedChannel = name;
      return {
        listen: (event, cb) => {
          eventCallback = cb;
        }
      };
    },
    leave: (name) => {
      leftChannel = name;
    }
  };

  const provider = new SynkkYjsProvider({
    doc,
    vaultId: 1,
    documentId: 77,
    echo: fakeEcho
  });

  await provider.connect();

  assert.equal(subscribedChannel, 'vault-collaboration.77');
  assert.ok(eventCallback);

  // Simulate remote update broadcast from Echo
  const remoteDoc = new Y.Doc();
  remoteDoc.getText('markdown').insert(0, 'Live Echo Edit');
  const payload = Buffer.from(Y.encodeStateAsUpdate(remoteDoc)).toString('base64');

  await eventCallback({
    document_id: 77,
    sequence: 1,
    client_update_id: 'echo-remote-1',
    payload: payload,
    is_encrypted: false
  });

  assert.equal(doc.getText('markdown').toString(), 'Live Echo Edit');
  assert.equal(provider.latestSequence, 1);

  // Duplicate broadcast is ignored
  await eventCallback({
    document_id: 77,
    sequence: 1,
    client_update_id: 'echo-remote-1',
    payload: payload,
    is_encrypted: false
  });

  assert.equal(doc.getText('markdown').toString(), 'Live Echo Edit');

  // Disconnect leaves channel
  provider.disconnect();
  assert.equal(leftChannel, 'vault-collaboration.77');

  provider.destroy();
});

