import * as Y from 'yjs';
import { Observable } from 'lib0/observable';
import {
  encodeEncryptedUpdate,
  decodeEncryptedUpdate,
  uint8ToBase64,
  base64ToUint8
} from './encrypted-update-codec.js';

export class SynkkAwareness extends Observable {
  /**
   * @param {Y.Doc} doc
   */
  constructor(doc) {
    super();
    this.doc = doc;
    this.clientID = doc.clientID;
    this.states = new Map();
    this.meta = new Map();
  }

  getLocalState() {
    return this.states.get(this.clientID) || null;
  }

  setLocalState(state) {
    const clientID = this.clientID;
    const curr = this.states.get(clientID);
    const currClock = (this.meta.get(clientID)?.clock || 0) + 1;
    this.meta.set(clientID, { clock: currClock, lastUpdated: Date.now() });

    if (state === null) {
      this.states.delete(clientID);
      this.emit('change', [{ added: [], updated: [], removed: [clientID] }, 'local']);
    } else {
      const isNew = curr === undefined;
      this.states.set(clientID, state);
      this.emit('change', [{ added: isNew ? [clientID] : [], updated: isNew ? [] : [clientID], removed: [] }, 'local']);
    }
    this.emit('update', [{ added: [clientID], updated: [], removed: [] }, 'local']);
  }

  setLocalStateField(field, value) {
    const state = Object.assign({}, this.getLocalState());
    state[field] = value;
    this.setLocalState(state);
  }

  getStates() {
    return this.states;
  }

  destroy() {
    this.emit('destroy', [this]);
    this.setLocalState(null);
    super.destroy();
  }
}

/**
 * Synkk Transport-Independent Yjs Provider
 *
 * Coordinates Yjs document synchronization over durable HTTP journals and
 * real-time Reverb WebSocket channels.
 * Supports zero-knowledge client-side E2EE with AAD authentication.
 */
export class SynkkYjsProvider {
  /**
   * @param {{
   *   doc: Y.Doc,
   *   vaultId: string|number,
   *   documentId?: string|number|null,
   *   path?: string,
   *   cryptoKey?: CryptoKey|null,
   *   e2eeEngine?: any,
   *   transport?: any,
   *   echo?: any,
   *   awareness?: any
   * }} options
   */
  constructor({
    doc,
    vaultId,
    documentId = null,
    path = '',
    cryptoKey = null,
    e2eeEngine = null,
    transport = null,
    echo = null,
    awareness = null,
  }) {
    if (!doc) {
      throw new Error('SynkkYjsProvider requires a Y.Doc instance.');
    }
    this.doc = doc;
    this.vaultId = vaultId;
    this.documentId = documentId;
    this.path = path;
    this.cryptoKey = cryptoKey;
    this.e2eeEngine = e2eeEngine;
    this.transport = transport;
    this.echo = echo;
    this.awareness = awareness || new SynkkAwareness(this.doc);
    this._ownsAwareness = !awareness;
    this._onAwarenessChange = null;
    this.echoChannel = null;

    this.connected = false;
    this.synced = false;
    this.locked = false;
    this._pollTimer = null;
    this.latestSequence = 0;
    this.appliedSequences = new Set();
    this.outbox = [];
    this._pendingOperations = [];
    this.providerOrigin = Symbol('synkk-yjs-provider');

    this._onDocUpdate = this.handleDocUpdate.bind(this);
    this.doc.on('update', this._onDocUpdate);
  }

  isEncrypted() {
    return this.cryptoKey !== null || (this.e2eeEngine && typeof this.e2eeEngine.isReady === 'function' && this.e2eeEngine.isReady());
  }

  getCryptoKey() {
    if (this.cryptoKey) return this.cryptoKey;
    if (this.e2eeEngine && typeof this.e2eeEngine.getKey === 'function') {
      return this.e2eeEngine.getKey();
    }
    return null;
  }

  async waitForPending() {
    while (this._pendingOperations.length > 0) {
      await Promise.all([...this._pendingOperations]);
    }
  }

  handleDocUpdate(update, origin) {
    if (origin === this.providerOrigin) {
      // Remote update applied locally; do not re-broadcast
      return;
    }

    if (this.locked) {
      throw new Error('Document is locked due to encryption/authentication failure.');
    }

    const promise = this.queueLocalUpdate(update);
    this._pendingOperations.push(promise);
    promise.finally(() => {
      const idx = this._pendingOperations.indexOf(promise);
      if (idx !== -1) {
        this._pendingOperations.splice(idx, 1);
      }
    });
  }

  async queueLocalUpdate(update) {
    if (this.locked) {
      throw new Error('Document is locked due to encryption/authentication failure.');
    }

    const clientUpdateId = typeof crypto !== 'undefined' && crypto.randomUUID
      ? crypto.randomUUID()
      : 'update-' + Date.now() + '-' + Math.random().toString(36).substring(2, 9);

    let envelope;
    if (this.isEncrypted()) {
      const key = this.getCryptoKey();
      if (!key) {
        this.locked = true;
        throw new Error('E2EE is enabled but encryption key is missing or not ready.');
      }
      const encoded = await encodeEncryptedUpdate(update, key, {
        vaultId: this.vaultId,
        documentId: this.documentId ?? 0,
        purpose: 'yjs-update',
        formatVersion: 2,
      });

      envelope = {
        document_id: this.documentId,
        path: this.path,
        client_update_id: clientUpdateId,
        payload: encoded.ciphertextBase64,
        payload_sha256: encoded.payloadSha256,
        iv: encoded.ivHex,
        tag: encoded.tagHex,
        encrypted: true,
        is_encrypted: true,
        format_version: 2,
      };
    } else {
      const payload = uint8ToBase64(update);
      envelope = {
        document_id: this.documentId,
        path: this.path,
        client_update_id: clientUpdateId,
        payload: payload,
        encrypted: false,
        is_encrypted: false,
        format_version: 2,
      };
    }

    const entry = {
      clientUpdateId,
      update,
      envelope,
      status: 'pending',
    };

    this.outbox.push(entry);

    if (this.connected && !this.locked) {
      await this.flushOutbox();
    }

    return clientUpdateId;
  }

  acknowledge(clientUpdateId, serverSequence = null) {
    const idx = this.outbox.findIndex((item) => item.clientUpdateId === clientUpdateId);
    if (idx !== -1) {
      this.outbox.splice(idx, 1);
    }

    if (serverSequence !== null && serverSequence !== undefined) {
      const seq = Number(serverSequence);
      if (!isNaN(seq)) {
        this.appliedSequences.add(seq);
        if (seq > this.latestSequence) {
          this.latestSequence = seq;
        }
      }
    }
  }

  async checkpoint() {
    if (!this.transport?.checkpoint) return null;
    const stateVector = Y.encodeStateVector(this.doc);
    const snapshot = Y.encodeStateAsUpdate(this.doc);
    let payloadBase64 = uint8ToBase64(snapshot);
    let isEncrypted = false;
    let ivHex = '';
    let tagHex = '';

    if (this.isEncrypted()) {
      const key = this.getCryptoKey();
      if (!key) {
        this.locked = true;
        throw new Error('E2EE is enabled but encryption key is missing or not ready.');
      }
      const encrypted = await encodeEncryptedUpdate(snapshot, key, {
        vaultId: this.vaultId,
        documentId: this.documentId ?? 0,
        purpose: 'checkpoint',
        formatVersion: 2,
      });
      payloadBase64 = encrypted.ciphertextBase64;
      ivHex = encrypted.ivHex;
      tagHex = encrypted.tagHex;
      isEncrypted = true;
    }

    return await this.transport.checkpoint({
      state_vector: uint8ToBase64(stateVector),
      checkpoint_snapshot: payloadBase64,
      is_encrypted: isEncrypted,
      encryption_iv: ivHex,
      encryption_tag: tagHex,
    });
  }

  async applyCatchUp(updates) {
    if (!Array.isArray(updates) || updates.length === 0) {
      return;
    }

    if (this.locked) {
      throw new Error('Cannot apply catch-up: document is locked.');
    }

    for (const updateRecord of updates) {
      const seq = updateRecord.sequence !== undefined && updateRecord.sequence !== null
        ? Number(updateRecord.sequence)
        : 0;
      if (seq > 0 && this.appliedSequences.has(seq)) {
        // Already applied
        continue;
      }

      const isEncrypted = Boolean(updateRecord.is_encrypted || updateRecord.encrypted);
      let plaintextBytes;

      if (isEncrypted) {
        if (!this.isEncrypted()) {
          this.locked = true;
          throw new Error('Received encrypted update but provider has no E2EE key.');
        }
        const key = this.getCryptoKey();
        try {
          plaintextBytes = await decodeEncryptedUpdate(
            {
              ciphertextBase64: updateRecord.payload,
              ivHex: updateRecord.encryption_iv ?? updateRecord.iv,
              tagHex: updateRecord.encryption_tag ?? updateRecord.tag,
              formatVersion: updateRecord.format_version ?? 2,
            },
            key,
            {
              vaultId: this.vaultId,
              documentId: this.documentId ?? updateRecord.document_id ?? 0,
              purpose: updateRecord.is_checkpoint ? 'checkpoint' : 'yjs-update',
            }
          );
        } catch (error) {
          // Authentication failure locks document and leaves durable sequence unacknowledged
          this.locked = true;
          throw error;
        }
      } else {
        if (this.isEncrypted()) {
          this.locked = true;
          throw new Error('Received unencrypted update on an encrypted document.');
        }
        plaintextBytes = base64ToUint8(updateRecord.payload);
      }

      // Apply with provider origin tag to prevent resend loops
      Y.applyUpdate(this.doc, plaintextBytes, this.providerOrigin);

      if (seq > 0) {
        this.appliedSequences.add(seq);
        if (seq > this.latestSequence) {
          this.latestSequence = seq;
        }
      }
    }
  }

  async flushOutbox() {
    const sendFn = this.transport ? (this.transport.sendUpdate || this.transport.appendUpdate) : null;
    if (!this.connected || this.locked || typeof sendFn !== 'function') {
      return;
    }

    const pending = this.outbox.filter((item) => item.status === 'pending');
    for (const entry of pending) {
      entry.status = 'sending';
      try {
        const result = await sendFn.call(this.transport, entry.envelope);
        if (result && result.acknowledged !== false) {
          const serverSeq = result.sequence ?? result.update?.sequence;
          this.acknowledge(entry.clientUpdateId, serverSeq);
        } else {
          entry.status = 'pending';
        }
      } catch (err) {
        entry.status = 'pending';
        throw err;
      }
    }
  }

  async connect() {
    if (this.locked) {
      throw new Error('Cannot connect locked document.');
    }

    // 1. Fetch durable catch-up first before declaring live
    if (this.transport && typeof this.transport.fetchUpdates === 'function') {
      const catchUpData = await this.transport.fetchUpdates(this.latestSequence);
      if (catchUpData) {
        if (catchUpData.document_id && !this.documentId) {
          this.documentId = catchUpData.document_id;
        }
        if (Array.isArray(catchUpData.updates)) {
          await this.applyCatchUp(catchUpData.updates);
        }
      }
    }

    // 2. Subscribe to private Reverb channel only after durable catch-up
    if (this.echo && this.documentId) {
      const channelName = `vault-collaboration.${this.documentId}`;
      this.echoChannel = this.echo.private(channelName);
      const onBroadcast = async (event) => {
        await this.handleBroadcastEvent(event);
      };
      this.echoChannel.listen('.VaultCollaborationUpdateCommitted', onBroadcast);
      this.echoChannel.listen('.update.committed', onBroadcast);

      if (typeof this.echoChannel.listenForWhisper === 'function') {
        this.echoChannel.listenForWhisper('awareness', (data) => {
          if (!data || data.clientID === this.awareness.clientID) return;
          const { clientID, state } = data;
          if (state === null) {
            if (this.awareness.states.has(clientID)) {
              this.awareness.states.delete(clientID);
              this.awareness.emit('change', [{ added: [], updated: [], removed: [clientID] }, 'remote']);
            }
          } else {
            const isNew = !this.awareness.states.has(clientID);
            this.awareness.states.set(clientID, state);
            this.awareness.emit('change', [{ added: isNew ? [clientID] : [], updated: isNew ? [] : [clientID], removed: [] }, 'remote']);
          }
        });
      }

      this._onAwarenessChange = ({ added, updated, removed }, origin) => {
        if (origin === 'local' && this.echoChannel && typeof this.echoChannel.whisper === 'function') {
          this.echoChannel.whisper('awareness', {
            clientID: this.awareness.clientID,
            state: this.awareness.getLocalState()
          });
        }
      };
      this.awareness.on('change', this._onAwarenessChange);
    }

    this.connected = true;
    this.synced = true;

    await this.flushOutbox();

    // 3. Fallback catch-up polling: ensure continuous convergence even if WebSockets are down or disconnected
    if (this.transport && typeof this.transport.fetchUpdates === 'function') {
      this._startPolling();
    }
  }

  _startPolling(intervalMs = 1500) {
    this._stopPolling();
    this._pollTimer = setInterval(async () => {
      if (!this.connected || this.locked || !this.transport?.fetchUpdates) return;
      try {
        const catchUpData = await this.transport.fetchUpdates(this.latestSequence);
        if (catchUpData?.updates && Array.isArray(catchUpData.updates) && catchUpData.updates.length > 0) {
          await this.applyCatchUp(catchUpData.updates);
        }
      } catch {}
    }, intervalMs);

    if (this._pollTimer && typeof this._pollTimer.unref === 'function') {
      this._pollTimer.unref();
    }
  }

  _stopPolling() {
    if (this._pollTimer) {
      clearInterval(this._pollTimer);
      this._pollTimer = null;
    }
  }

  async handleBroadcastEvent(event) {
    if (!event || this.locked) return;

    const seq = event.sequence !== undefined && event.sequence !== null
      ? Number(event.sequence)
      : 0;

    // Deduplicate by server sequence
    if (seq > 0 && this.appliedSequences.has(seq)) {
      return;
    }

    // Check if this is an echo of our own outbox write
    if (event.client_update_id) {
      const pending = this.outbox.find((item) => item.clientUpdateId === event.client_update_id);
      if (pending) {
        this.acknowledge(event.client_update_id, seq);
        return;
      }
    }

    // If gap detected, fetch missing updates from server journal first
    if (seq > this.latestSequence + 1 && this.transport?.fetchUpdates) {
      const catchUpData = await this.transport.fetchUpdates(this.latestSequence);
      if (catchUpData?.updates) {
        await this.applyCatchUp(catchUpData.updates);
      }
    }

    await this.applyCatchUp([event]);
  }

  disconnect() {
    this._stopPolling();
    if (this._onAwarenessChange && this.awareness) {
      this.awareness.off('change', this._onAwarenessChange);
      this._onAwarenessChange = null;
    }
    if (this.echoChannel && typeof this.echoChannel.whisper === 'function' && this.awareness) {
      try {
        this.echoChannel.whisper('awareness', {
          clientID: this.awareness.clientID,
          state: null
        });
      } catch {}
    }
    if (this.echo && this.documentId) {
      this.echo.leave(`vault-collaboration.${this.documentId}`);
      this.echoChannel = null;
    }
    this.connected = false;
    this.synced = false;
  }

  destroy() {
    this._stopPolling();
    this.disconnect();
    this.doc.off('update', this._onDocUpdate);
    this.outbox = [];
    if (this._ownsAwareness && this.awareness) {
      this.awareness.destroy();
    }
  }
}
