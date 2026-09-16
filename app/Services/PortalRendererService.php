<?php

namespace App\Services;

use App\Models\VaultFile;
use App\Models\VaultPortal;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class PortalRendererService
{
    /**
     * Parse raw markdown content into frontmatter metadata and markdown body.
     *
     * @return array{frontmatter: array<string, mixed>, body: string}
     */
    public function parseFrontmatter(string $rawContent): array
    {
        $rawContent = str_replace(["\r\n", "\r"], "\n", $rawContent);

        if (! str_starts_with($rawContent, "---\n")) {
            return [
                'frontmatter' => [],
                'body' => $rawContent,
            ];
        }

        $parts = preg_split("/\n---\n/", substr($rawContent, 4), 2);

        if ($parts === false || count($parts) < 2) {
            return [
                'frontmatter' => [],
                'body' => $rawContent,
            ];
        }

        $yamlString = trim($parts[0]);
        $body = trim($parts[1]);

        try {
            $frontmatter = Yaml::parse($yamlString);
            if (! is_array($frontmatter)) {
                $frontmatter = [];
            }
        } catch (\Throwable) {
            $frontmatter = [];
        }

        return [
            'frontmatter' => $frontmatter,
            'body' => $body,
        ];
    }

    /**
     * Render note markdown into rich interactive HTML with callouts, wikilinks, and TOC.
     *
     * @param  Collection<int, VaultFile>  $allFiles
     * @return array{
     *     html: string,
     *     toc: array<int, array{level: int, text: string, id: string}>,
     *     reading_time: string,
     *     word_count: int,
     *     frontmatter: array<string, mixed>,
     *     backlinks: array<int, array{path: string, title: string}>
     * }
     */
    public function renderNoteHtml(string $rawContent, VaultPortal $portal, Collection $allFiles, ?VaultFile $currentFile = null): array
    {
        $parsed = $this->parseFrontmatter($rawContent);
        $frontmatter = $parsed['frontmatter'];
        $body = $parsed['body'];

        // 1. Calculate reading stats
        $cleanText = strip_tags($body);
        $wordCount = str_word_count($cleanText);
        $readingMinutes = max(1, (int) ceil($wordCount / 200));
        $readingTime = "{$readingMinutes} min read";

        // 2. Extract Table of Contents and inject anchor IDs into headings
        $toc = [];
        $processedBody = preg_replace_callback('/^(#{1,4})\s+(.+)$/m', function ($matches) use (&$toc) {
            $level = strlen($matches[1]);
            $rawText = trim($matches[2]);
            $cleanTitle = trim(preg_replace('/\[\[(.*?)\]\]/', '$1', $rawText));
            $slug = Str::slug($cleanTitle);

            // Avoid duplicate anchor slugs
            $baseSlug = $slug;
            $counter = 1;
            $existingIds = array_column($toc, 'id');
            while (in_array($slug, $existingIds, true)) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            $toc[] = [
                'level' => $level,
                'text' => $cleanTitle,
                'id' => $slug,
            ];

            return sprintf('%s <a id="%s" class="anchor-link scroll-mt-24"></a>%s', $matches[1], $slug, $rawText);
        }, $body);

        $processedBody = $processedBody ?? $body;

        // 3. Transform Obsidian Callouts (> [!NOTE])
        $processedBody = $this->renderCallouts($processedBody);

        // 4. Resolve [[Wikilinks]] with interactive previews
        $processedBody = $this->renderWikilinks($processedBody, $portal, $allFiles);

        // 5. Convert Markdown to HTML via Laravel CommonMark engine
        $html = Str::markdown($processedBody, [
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);

        // 6. Post-process code blocks into macOS styled windows
        $html = $this->renderCodeWindows($html);

        // 7. Post-process tables into responsive styled wrappers
        $html = $this->renderTableWrappers($html);

        // 8. Compute Backlinks for current note
        $backlinks = [];
        if ($currentFile) {
            $currentBasename = pathinfo($currentFile->path, PATHINFO_FILENAME);
            foreach ($allFiles as $file) {
                if ($file->id === $currentFile->id || ! $file->isMarkdown()) {
                    continue;
                }
                $content = $file->getContents();
                if ($content && (str_contains($content, "[[{$currentBasename}]]") || str_contains($content, "[[{$currentBasename}|") || str_contains($content, "[[{$currentFile->path}]]"))) {
                    $backlinks[] = [
                        'path' => $file->path,
                        'title' => pathinfo($file->path, PATHINFO_FILENAME),
                    ];
                }
            }
        }

        return [
            'html' => $html,
            'toc' => $toc,
            'reading_time' => $readingTime,
            'word_count' => $wordCount,
            'frontmatter' => $frontmatter,
            'backlinks' => $backlinks,
        ];
    }

    /**
     * Post-process code blocks into macOS styled windows with traffic lights and copy action.
     */
    protected function renderCodeWindows(string $html): string
    {
        $pattern = '/<pre><code(?:\s+class="language-([a-zA-Z0-9_+-]+)")?>(.*?)<\/code><\/pre>/s';

        return preg_replace_callback($pattern, function ($matches) {
            $lang = ! empty($matches[1]) ? strtolower(trim($matches[1])) : 'code';
            $code = $matches[2];
            $displayLang = e(strtoupper($lang));

            return <<<HTML
<div class="synkk-code-window">
    <div class="synkk-code-bar">
        <div class="synkk-traffic-lights">
            <span class="synkk-traffic-dot synkk-dot-red"></span>
            <span class="synkk-traffic-dot synkk-dot-yellow"></span>
            <span class="synkk-traffic-dot synkk-dot-green"></span>
        </div>
        <span class="synkk-code-lang">{$displayLang}</span>
        <button type="button" class="synkk-code-copy-btn" onclick="copyCode(this)" title="Copy code">
            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
            <span>Copy</span>
        </button>
    </div>
    <pre><code class="language-{$lang}">{$code}</code></pre>
</div>
HTML;
        }, $html) ?? $html;
    }

    /**
     * Wrap HTML tables in responsive glass container for proper horizontal scrolling.
     */
    protected function renderTableWrappers(string $html): string
    {
        return preg_replace_callback('/<table(?:\s+[^>]*)?>.*?<\/table>/s', function ($matches) {
            return '<div class="synkk-prose-table-container">'.$matches[0].'</div>';
        }, $html) ?? $html;
    }

    /**
     * Render Obsidian Callouts (> [!NOTE]) into styled HTML blocks.
     */
    protected function renderCallouts(string $text): string
    {
        $pattern = '/^>\s*\[!([A-Za-z]+)\]([+-]?)(?:[ \t]+([^\n]*))?\n((?:>[ \t]*[^\n]*\n?)*)/m';

        return preg_replace_callback($pattern, function ($matches) {
            $type = strtoupper(trim($matches[1]));
            $title = ! empty(trim($matches[3] ?? '')) ? trim($matches[3]) : ucfirst(strtolower($type));
            $rawBodyLines = explode("\n", $matches[4]);

            $cleanBody = [];
            foreach ($rawBodyLines as $line) {
                $cleanBody[] = preg_replace('/^>[ \t]?/', '', $line);
            }
            $innerContent = trim(implode("\n", $cleanBody));

            $classType = match ($type) {
                'TIP', 'SUCCESS', 'CHECK', 'DONE' => 'tip',
                'WARNING', 'CAUTION', 'ATTENTION' => 'warning',
                'DANGER', 'ERROR', 'BUG', 'FAILURE' => 'danger',
                'IMPORTANT', 'QUESTION', 'HELP', 'FAQ' => 'important',
                default => 'note',
            };

            $iconSvg = match ($classType) {
                'tip' => '<svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
                'warning' => '<svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>',
                'danger' => '<svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>',
                'important' => '<svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>',
                default => '<svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>',
            };

            $safeTitle = e($title);
            $parsedInner = Str::markdown($innerContent);

            return <<<HTML
<div class="synkk-callout synkk-callout--{$classType}">
    <div class="synkk-callout-header">
        {$iconSvg}
        <span>{$safeTitle}</span>
    </div>
    <div class="synkk-callout-body">
        {$parsedInner}
    </div>
</div>
HTML;
        }, $text) ?? $text;
    }

    /**
     * Resolve Obsidian [[Wikilinks]] into interactive hover preview anchors.
     *
     * @param  Collection<int, VaultFile>  $allFiles
     */
    protected function renderWikilinks(string $text, VaultPortal $portal, Collection $allFiles): string
    {
        return preg_replace_callback('/\[\[(.*?)\]\]/', function ($matches) use ($allFiles) {
            $parts = explode('|', $matches[1]);
            $target = trim($parts[0]);
            $label = isset($parts[1]) ? trim($parts[1]) : $target;
            $safeLabel = e($label);

            // Match against files by exact path, basename, or path ending
            $matchedFile = $allFiles->first(function (VaultFile $f) use ($target) {
                $basename = pathinfo($f->path, PATHINFO_FILENAME);

                return strcasecmp($basename, $target) === 0
                    || strcasecmp($f->path, $target) === 0
                    || strcasecmp($f->path, "{$target}.md") === 0;
            });

            if ($matchedFile) {
                $targetPath = urlencode($matchedFile->path);
                $previewTitle = e(pathinfo($matchedFile->path, PATHINFO_FILENAME));
                $excerpt = '';
                if ($matchedFile->isMarkdown()) {
                    $c = $matchedFile->getContents() ?? '';
                    $parsedFm = $this->parseFrontmatter($c);
                    if (! empty($parsedFm['frontmatter']['title'])) {
                        $previewTitle = e($parsedFm['frontmatter']['title']);
                    }
                    $excerpt = e($this->extractExcerpt($c, 140));
                }

                return <<<HTML
<a href="?note={$targetPath}"
   wire:navigate
   class="synkk-wikilink inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-semibold text-amber-600 dark:text-amber-400 bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/20 transition-colors"
   data-preview-title="{$previewTitle}"
   data-preview-excerpt="{$excerpt}"
   data-preview-path="{$targetPath}"
   x-on:mouseenter="showPreview(\$event, '{$previewTitle}', '{$excerpt}')"
   x-on:mouseleave="hidePreview()"
>
    <span>{$safeLabel}</span>
    <svg class="size-3 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
    </svg>
</a>
HTML;
            }

            return "<span class=\"inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium text-zinc-500 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700/60\">[[{$safeLabel}]]</span>";
        }, $text) ?? $text;
    }

    /**
     * Extract a clean text excerpt from markdown, stripping frontmatter and code blocks.
     */
    public function extractExcerpt(string $rawContent, int $length = 160): string
    {
        $parsed = $this->parseFrontmatter($rawContent);
        $body = $parsed['body'];

        // Strip code fences
        $body = preg_replace('/```.*?```/s', '', $body) ?? $body;
        // Strip inline code
        $body = preg_replace('/`.*?`/', '', $body) ?? $body;
        // Strip images and links
        $body = preg_replace('/!\[.*?\]\(.*?\)/', '', $body) ?? $body;
        $body = preg_replace('/\[(.*?)\]\(.*?\)/', '$1', $body) ?? $body;
        $body = preg_replace('/\[\[(.*?)\]\]/', '$1', $body) ?? $body;
        // Strip headers and list markers
        $body = preg_replace('/^[#*>-]+\s+/m', '', $body) ?? $body;

        $clean = trim(preg_replace('/\s+/', ' ', strip_tags($body)));

        return Str::limit($clean, $length);
    }
}
