<?php
declare(strict_types=1);

namespace Heartbeat\Blocks;

use Heartbeat\Auth\Auth;
use Heartbeat\Security\Security;

/**
 * Structured JSON Block Renderer
 * Transforms a JSON block tree into semantic, responsive HTML styled with Tailwind CSS.
 */
final class BlockRenderer
{
    private static ?BlockRenderer $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Render an entire block tree (array of blocks or JSON string) into HTML
     *
     * @param array<array<string, mixed>>|string $blocks
     * @param array<string, mixed> $context
     */
    public function render(array|string $blocks, array $context = []): string
    {
        if (is_string($blocks)) {
            $decoded = json_decode($blocks, true);
            $blocks = is_array($decoded) ? $decoded : [];
        }

        if (empty($blocks)) {
            return '';
        }

        $html = '';
        $i = 0;
        $count = count($blocks);

        while ($i < $count) {
            $block = $blocks[$i];
            if (!is_array($block) || empty($block['type'])) {
                $i++;
                continue;
            }

            // Group consecutive core/card blocks into a responsive grid
            if ($block['type'] === 'core/card') {
                $cardGroup = [];
                while ($i < $count && is_array($blocks[$i]) && ($blocks[$i]['type'] ?? '') === 'core/card') {
                    $cardGroup[] = $blocks[$i];
                    $i++;
                }

                $cardCount = count($cardGroup);
                $groupCols = null;
                foreach ($cardGroup as $cBlock) {
                    if (isset($cBlock['attrs']['columns']) && (int)$cBlock['attrs']['columns'] > 0) {
                        $groupCols = (int)$cBlock['attrs']['columns'];
                        break;
                    }
                }

                if ($groupCols !== null && (int)$groupCols > 0) {
                    $cols = (int)$groupCols;
                } else {
                    $cols = match ($cardCount) {
                        1 => 1,
                        2 => 2,
                        4 => 4,
                        default => 3,
                    };
                }

                $gridColsClass = match ($cols) {
                    1 => 'grid-cols-1',
                    2 => 'grid-cols-1 md:grid-cols-2',
                    4 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
                    default => 'grid-cols-1 md:grid-cols-3',
                };

                $cardsHtml = '';
                foreach ($cardGroup as $cBlock) {
                    $cardsHtml .= $this->renderCard((array)($cBlock['attrs'] ?? []), (array)($cBlock['styles'] ?? []));
                }

                $html .= "<div class=\"grid {$gridColsClass} gap-6 my-8\">{$cardsHtml}</div>";
                continue;
            }

            $html .= $this->renderBlock($block, $context);
            $i++;
        }

        return $html;
    }

    /**
     * Render a single block
     * @param array<string, mixed> $block
     * @param array<string, mixed> $context
     */
    public function renderBlock(array $block, array $context = []): string
    {
        $type = (string)($block['type'] ?? '');
        $attrs = (array)($block['attrs'] ?? []);
        $styles = (array)($block['styles'] ?? []);
        $innerBlocks = (array)($block['innerBlocks'] ?? []);

        return match ($type) {
            'core/hero'                           => $this->renderHero($attrs, $styles),
            'core/heading'                        => $this->renderHeading($attrs, $styles),
            'core/paragraph'                      => $this->renderParagraph($attrs, $styles),
            'core/image'                          => $this->renderImage($attrs, $styles),
            'core/button'                         => $this->renderButton($attrs, $styles),
            'core/columns'                        => $this->renderColumns($attrs, $styles, $innerBlocks, $context),
            'core/card'                           => $this->renderCard($attrs, $styles),
            'core/features', 'core/features-grid' => $this->renderFeatures($attrs, $styles),
            'core/cta-banner'                     => $this->renderCtaBanner($attrs, $styles),
            'core/faq'                            => $this->renderFaq($attrs, $styles),
            'core/quote'                          => $this->renderQuote($attrs, $styles),
            'core/alert'                          => $this->renderAlert($attrs, $styles),
            'core/spacer'                         => $this->renderSpacer($attrs, $styles),
            'core/popup'                          => $this->renderPopup($attrs, $styles),
            default                               => $this->renderCustomBlock($type, $attrs, $styles, $innerBlocks, $context),
        };
    }

    private function renderHero(array $attrs, array $styles): string
    {
        $headline = Security::e((string)($attrs['headline'] ?? ''));
        $subheadline = Security::e((string)($attrs['subheadline'] ?? ''));
        $badge = Security::e((string)($attrs['badge'] ?? ''));
        $btn1Text = Security::e((string)($attrs['primary_btn_text'] ?? ($attrs['cta_text'] ?? '')));
        $btn1Url = Security::e((string)($attrs['primary_btn_url'] ?? ($attrs['cta_link'] ?? '#')));
        $btn2Text = Security::e((string)($attrs['secondary_btn_text'] ?? ($attrs['secondary_text'] ?? '')));
        $btn2Url = Security::e((string)($attrs['secondary_btn_url'] ?? ($attrs['secondary_link'] ?? '#')));
        $align = ($attrs['alignment'] ?? 'center') === 'left' ? 'text-left' : 'text-center';
        $alignFlex = ($attrs['alignment'] ?? 'center') === 'left' ? 'justify-start' : 'justify-center';
        $bgType = $attrs['bg_type'] ?? 'gradient';
        $customClass = Security::e((string)($styles['class'] ?? ''));

        $badgeHtml = $badge !== ''
            ? "<div class=\"inline-flex items-center space-x-2 px-3.5 py-1.5 rounded-full border text-xs font-semibold mb-6 text-hb-primary\" style=\"background-color: color-mix(in srgb, var(--hb-primary) 10%, transparent); border-color: color-mix(in srgb, var(--hb-primary) 20%, transparent); color: var(--hb-primary);\"><span>●</span><span>{$badge}</span></div>"
            : '';

        $btn1Html = $btn1Text !== ''
            ? "<a href=\"{$btn1Url}\" class=\"inline-flex items-center space-x-2 px-6 py-3.5 rounded-xl bg-hb-primary bg-hb-primary-hover text-white font-semibold text-sm transition shadow-lg\"><span>{$btn1Text}</span><span>&rarr;</span></a>"
            : '';

        $btn2Html = $btn2Text !== ''
            ? "<a href=\"{$btn2Url}\" class=\"inline-flex items-center space-x-2 px-6 py-3.5 rounded-xl bg-[#1e293b] hover:bg-[#334155] text-slate-300 hover:text-white font-medium text-sm transition\"><span>{$btn2Text}</span></a>"
            : '';

        $buttonsHtml = ($btn1Html !== '' || $btn2Html !== '')
            ? "<div class=\"flex flex-wrap items-center {$alignFlex} gap-4 mt-8\">{$btn1Html}{$btn2Html}</div>"
            : '';

        return <<<HTML
<section class="py-16 md:py-24 relative overflow-hidden {$align} {$customClass}">
    <div class="max-w-5xl mx-auto px-6 relative z-10">
        {$badgeHtml}
        <h1 class="text-3xl sm:text-5xl md:text-6xl font-black text-white tracking-tight leading-tight mb-6">
            {$headline}
        </h1>
        <p class="text-base sm:text-lg text-slate-400 max-w-2xl mx-auto leading-relaxed">
            {$subheadline}
        </p>
        {$buttonsHtml}
    </div>
</section>
HTML;
    }

    private function renderHeading(array $attrs, array $styles): string
    {
        $level = in_array($attrs['level'] ?? 'h2', ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ? $attrs['level'] : 'h2';
        $content = Security::e((string)($attrs['content'] ?? ''));
        $subtitle = Security::e((string)($attrs['subtitle'] ?? ''));
        $align = match ($attrs['alignment'] ?? 'left') {
            'center' => 'text-center',
            'right'  => 'text-right',
            default  => 'text-left',
        };
        $customClass = Security::e((string)($styles['class'] ?? ''));

        $sizeClasses = match ($level) {
            'h1' => 'text-3xl sm:text-4xl font-black tracking-tight mb-3',
            'h2' => 'text-2xl sm:text-3xl font-extrabold tracking-tight mb-2',
            'h3' => 'text-xl sm:text-2xl font-bold tracking-tight mb-2',
            'h4' => 'text-lg sm:text-xl font-bold mb-1',
            'h5' => 'text-base font-semibold mb-1',
            'h6' => 'text-sm font-semibold mb-1',
        };

        $subtitleHtml = $subtitle !== ''
            ? "<p class=\"text-xs sm:text-sm font-semibold uppercase tracking-wider text-hb-primary mb-2\" style=\"color: var(--hb-primary);\">{$subtitle}</p>"
            : '';

        return <<<HTML
<div class="my-6 {$align} {$customClass}">
    {$subtitleHtml}
    <{$level} class="{$sizeClasses} text-white">{$content}</{$level}>
</div>
HTML;
    }

    private function renderParagraph(array $attrs, array $styles): string
    {
        $raw = (string)($attrs['content'] ?? '');
        $lead = !empty($attrs['lead']);
        $align = match ($attrs['alignment'] ?? 'left') {
            'center' => 'text-center',
            'right'  => 'text-right',
            default  => 'text-left',
        };
        $customClass = Security::e((string)($styles['class'] ?? ''));

        // Basic formatting allowance: line breaks to <br> or safe html paragraphs
        $escaped = nl2br(Security::e($raw));

        $classes = $lead
            ? 'text-base sm:text-lg text-slate-300 leading-relaxed font-normal mb-4'
            : 'text-sm sm:text-base text-slate-400 leading-relaxed font-normal mb-4';

        return "<p class=\"{$classes} {$align} {$customClass}\">{$escaped}</p>";
    }

    private function renderImage(array $attrs, array $styles): string
    {
        $url = Security::e((string)($attrs['url'] ?? ''));
        $alt = Security::e((string)($attrs['alt'] ?? ''));
        $caption = Security::e((string)($attrs['caption'] ?? ''));
        $aspect = ($attrs['aspect'] ?? 'video') === 'square' ? 'aspect-square' : 'aspect-video';
        $rounded = match ($attrs['rounded'] ?? '2xl') {
            'none'  => 'rounded-none',
            'lg'    => 'rounded-lg',
            'full'  => 'rounded-full',
            default => 'rounded-2xl',
        };
        $shadow = !empty($attrs['shadow']) ? 'shadow-2xl' : '';
        $link = Security::e((string)($attrs['link_url'] ?? ''));
        $customClass = Security::e((string)($styles['class'] ?? ''));

        $imgHtml = "<img src=\"{$url}\" alt=\"{$alt}\" loading=\"lazy\" class=\"w-full h-full object-cover {$rounded} border border-[#1e293b]\">";

        if ($link !== '') {
            $imgHtml = "<a href=\"{$link}\" class=\"block\">{$imgHtml}</a>";
        }

        $captionHtml = $caption !== ''
            ? "<figcaption class=\"text-xs text-slate-400 text-center mt-2.5\">{$caption}</figcaption>"
            : '';

        return <<<HTML
<figure class="my-8 overflow-hidden {$shadow} {$customClass}">
    <div class="relative w-full {$aspect} overflow-hidden {$rounded}">
        {$imgHtml}
    </div>
    {$captionHtml}
</figure>
HTML;
    }

    private function renderButton(array $attrs, array $styles): string
    {
        $text = Security::e((string)($attrs['text'] ?? 'Button'));
        $url = Security::e((string)($attrs['url'] ?? '#'));
        $target = ($attrs['target'] ?? '_self') === '_blank' ? '_blank' : '_self';
        $variant = $attrs['variant'] ?? 'primary';
        $size = $attrs['size'] ?? 'md';
        $align = match ($attrs['alignment'] ?? 'left') {
            'center' => 'justify-center',
            'right'  => 'justify-end',
            default  => 'justify-start',
        };
        $customClass = Security::e((string)($styles['class'] ?? ''));

        $sizeClasses = match ($size) {
            'sm'    => 'px-3.5 py-2 text-xs',
            'lg'    => 'px-7 py-4 text-base',
            default => 'px-5 py-3 text-sm',
        };

        $variantClasses = match ($variant) {
            'secondary' => 'bg-[#1e293b] hover:bg-[#334155] text-slate-200 border border-slate-700',
            'outline'   => 'bg-transparent text-hb-primary border border-hb-primary hover:bg-slate-800/40',
            'ghost'     => 'bg-transparent hover:bg-slate-800 text-slate-300 hover:text-white',
            default     => 'bg-hb-primary bg-hb-primary-hover text-white shadow-lg',
        };

        return <<<HTML
<div class="flex {$align} my-4 {$customClass}">
    <a href="{$url}" target="{$target}" class="inline-flex items-center space-x-2 font-semibold rounded-xl transition {$sizeClasses} {$variantClasses}">
        <span>{$text}</span>
        <span>&rarr;</span>
    </a>
</div>
HTML;
    }

    private function renderColumns(array $attrs, array $styles, array $innerBlocks, array $context): string
    {
        $cols = (int)($attrs['columns'] ?? 3);
        $gridColsClass = match ($cols) {
            1       => 'grid-cols-1',
            2       => 'grid-cols-1 md:grid-cols-2',
            4       => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
            default => 'grid-cols-1 md:grid-cols-3',
        };

        $gap = match ($attrs['gap'] ?? 'md') {
            'sm'    => 'gap-3',
            'lg'    => 'gap-8',
            default => 'gap-6',
        };

        $innerHtml = '';
        foreach ($innerBlocks as $innerBlock) {
            $innerHtml .= '<div>' . $this->renderBlock($innerBlock, $context) . '</div>';
        }

        $customClass = Security::e((string)($styles['class'] ?? ''));

        return <<<HTML
<div class="grid {$gridColsClass} {$gap} my-8 {$customClass}">
    {$innerHtml}
</div>
HTML;
    }

    private function renderCard(array $attrs, array $styles): string
    {
        $icon = Security::e((string)($attrs['icon'] ?? '⚡'));
        $title = Security::e((string)($attrs['title'] ?? ''));
        $description = Security::e((string)($attrs['description'] ?? ''));
        $badge = Security::e((string)($attrs['badge'] ?? ''));
        $link = Security::e((string)($attrs['link_url'] ?? ''));
        $customClass = Security::e((string)($styles['class'] ?? ''));

        $badgeHtml = $badge !== ''
            ? "<span class=\"text-[11px] font-bold px-2 py-0.5 rounded border text-hb-primary\" style=\"background-color: color-mix(in srgb, var(--hb-primary) 10%, transparent); border-color: color-mix(in srgb, var(--hb-primary) 20%, transparent); color: var(--hb-primary);\">{$badge}</span>"
            : '';

        $titleHtml = $link !== ''
            ? "<a href=\"{$link}\" class=\"hover:text-white transition\">{$title}</a>"
            : $title;

        return <<<HTML
<div class="p-6 bg-[#0f172a] border border-[#1e293b] rounded-2xl hover:border-slate-700 transition flex flex-col justify-between {$customClass}">
    <div>
        <div class="flex items-center justify-between mb-4">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center font-bold text-lg text-hb-primary" style="background-color: color-mix(in srgb, var(--hb-primary) 10%, transparent); color: var(--hb-primary);">
                {$icon}
            </div>
            {$badgeHtml}
        </div>
        <h3 class="text-lg font-bold text-white mb-2">{$titleHtml}</h3>
        <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">{$description}</p>
    </div>
</div>
HTML;
    }

    private function renderFeatures(array $attrs, array $styles): string
    {
        $cols = (int)($attrs['columns'] ?? 3);
        $gridColsClass = match ($cols) {
            1       => 'grid-cols-1',
            2       => 'grid-cols-1 md:grid-cols-2',
            4       => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
            default => 'grid-cols-1 md:grid-cols-3',
        };

        $title = Security::e((string)($attrs['title'] ?? ''));
        $titleHtml = $title !== ''
            ? "<h2 class=\"text-2xl sm:text-3xl font-black text-white text-center mb-8 tracking-tight\">{$title}</h2>"
            : '';

        $items = (array)($attrs['items'] ?? ($attrs['features'] ?? []));
        $itemsHtml = '';

        foreach ($items as $item) {
            $icon = Security::e((string)($item['icon'] ?? '⚡'));
            $itemTitle = Security::e((string)($item['title'] ?? ''));
            $desc = Security::e((string)($item['description'] ?? ''));

            $itemsHtml .= <<<HTML
<div class="p-6 bg-[#0f172a] border border-[#1e293b] rounded-2xl hover:border-slate-700 transition">
    <div class="w-10 h-10 rounded-xl flex items-center justify-center font-bold text-base mb-4 text-hb-primary" style="background-color: color-mix(in srgb, var(--hb-primary) 10%, transparent); color: var(--hb-primary);">
        {$icon}
    </div>
    <h4 class="text-base font-bold text-white mb-2">{$itemTitle}</h4>
    <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">{$desc}</p>
</div>
HTML;
        }

        $customClass = Security::e((string)($styles['class'] ?? ''));

        return <<<HTML
<div class="my-12 {$customClass}">
    {$titleHtml}
    <div class="grid {$gridColsClass} gap-6">
        {$itemsHtml}
    </div>
</div>
HTML;
    }

    private function renderCtaBanner(array $attrs, array $styles): string
    {
        $headline = Security::e((string)($attrs['headline'] ?? ''));
        $subheadline = Security::e((string)($attrs['subheadline'] ?? ''));
        $button = Security::e((string)($attrs['button'] ?? ($attrs['btn_text'] ?? 'Jetzt starten')));
        $link = Security::e((string)($attrs['link'] ?? ($attrs['btn_url'] ?? '#contact')));
        $customClass = Security::e((string)($styles['class'] ?? ''));

        $subHtml = $subheadline !== ''
            ? "<p class=\"text-sm text-slate-300 max-w-xl mx-auto mb-6 leading-relaxed\">{$subheadline}</p>"
            : '';

        return <<<HTML
<div class="my-10 p-8 sm:p-12 rounded-3xl bg-gradient-to-r from-[#141e33] via-[#1a1435] to-[#251025] border border-[#1e293b] text-center shadow-2xl relative overflow-hidden {$customClass}">
    <div class="absolute -top-12 -right-12 w-48 h-48 rounded-full blur-2xl pointer-events-none" style="background-color: var(--hb-primary); opacity: 0.15;"></div>
    <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight mb-3">{$headline}</h2>
    {$subHtml}
    <div class="mt-6">
        <a href="{$link}" class="inline-flex items-center space-x-2 px-8 py-3.5 rounded-xl bg-hb-primary bg-hb-primary-hover text-white font-bold text-sm shadow-xl transition transform hover:scale-105">
            <span>{$button}</span>
            <span>&rarr;</span>
        </a>
    </div>
</div>
HTML;
    }

    private function renderFaq(array $attrs, array $styles): string
    {
        $items = (array)($attrs['items'] ?? []);
        $itemsJson = htmlspecialchars(json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES, 'UTF-8');
        $customClass = Security::e((string)($styles['class'] ?? ''));

        return <<<HTML
<div class="my-10 max-w-3xl mx-auto space-y-3 {$customClass}"
     x-data="{ activeIndex: null, items: {$itemsJson} }">
    <template x-for="(item, index) in items" :key="index">
        <div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl overflow-hidden transition">
            <button @click="activeIndex = activeIndex === index ? null : index"
                    class="w-full p-5 text-left flex items-center justify-between text-sm sm:text-base font-semibold text-white hover:text-white transition">
                <span x-text="item.question"></span>
                <span class="ml-4 transform transition-transform text-hb-primary font-bold"
                      style="color: var(--hb-primary);"
                      :class="activeIndex === index ? 'rotate-180' : ''">&darr;</span>
            </button>
            <div x-show="activeIndex === index" x-cloak class="px-5 pb-5 pt-1 text-xs sm:text-sm text-slate-400 leading-relaxed border-t border-[#1e293b]/60">
                <span x-text="item.answer"></span>
            </div>
        </div>
    </template>
</div>
HTML;
    }

    private function renderQuote(array $attrs, array $styles): string
    {
        $quote = Security::e((string)($attrs['quote'] ?? ''));
        $author = Security::e((string)($attrs['author'] ?? ''));
        $role = Security::e((string)($attrs['role'] ?? ''));
        $avatar = Security::e((string)($attrs['avatar_url'] ?? ''));
        $rating = max(1, min(5, (int)($attrs['rating'] ?? 5)));
        $customClass = Security::e((string)($styles['class'] ?? ''));

        $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);

        $avatarHtml = $avatar !== ''
            ? "<img src=\"{$avatar}\" alt=\"{$author}\" class=\"w-10 h-10 rounded-full object-cover border border-slate-700\">"
            : "<div class=\"w-10 h-10 rounded-full border text-hb-primary flex items-center justify-center font-bold text-xs\" style=\"background-color: color-mix(in srgb, var(--hb-primary) 10%, transparent); border-color: color-mix(in srgb, var(--hb-primary) 20%, transparent); color: var(--hb-primary);\">" . strtoupper(substr($author, 0, 2)) . "</div>";

        return <<<HTML
<div class="my-8 p-8 bg-[#0f172a] border border-[#1e293b] rounded-2xl shadow-xl max-w-3xl mx-auto text-center {$customClass}">
    <div class="text-amber-400 text-lg mb-4 tracking-widest">{$stars}</div>
    <blockquote class="text-base sm:text-lg text-slate-200 italic font-medium leading-relaxed mb-6">
        &ldquo;{$quote}&rdquo;
    </blockquote>
    <div class="flex items-center justify-center space-x-3">
        {$avatarHtml}
        <div class="text-left">
            <div class="text-sm font-bold text-white">{$author}</div>
            <div class="text-xs text-slate-400">{$role}</div>
        </div>
    </div>
</div>
HTML;
    }

    private function renderAlert(array $attrs, array $styles): string
    {
        $type = $attrs['type'] ?? 'info';
        $title = Security::e((string)($attrs['title'] ?? ''));
        $message = Security::e((string)($attrs['message'] ?? ''));
        $customClass = Security::e((string)($styles['class'] ?? ''));

        $colorClasses = match ($type) {
            'success' => 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400',
            'warning' => 'bg-amber-500/10 border-amber-500/30 text-amber-400',
            'alert'   => 'bg-rose-500/10 border-rose-500/30 text-rose-400',
            default   => 'text-hb-primary border',
        };
        $alertStyle = ($type !== 'success' && $type !== 'warning' && $type !== 'alert')
            ? 'style="background-color: color-mix(in srgb, var(--hb-primary) 10%, transparent); border-color: color-mix(in srgb, var(--hb-primary) 30%, transparent); color: var(--hb-primary);"'
            : '';

        $icon = match ($type) {
            'success' => '✓',
            'warning' => '⚠️',
            'alert'   => '✕',
            default   => 'ℹ️',
        };

        $titleHtml = $title !== '' ? "<h4 class=\"font-bold text-sm mb-1\">{$title}</h4>" : '';

        return <<<HTML
<div class="my-6 p-4 rounded-xl border flex items-start space-x-3 {$colorClasses} {$customClass}" {$alertStyle}>
    <div class="font-bold text-base mt-0.5">{$icon}</div>
    <div class="text-xs leading-relaxed text-slate-200">
        {$titleHtml}
        <div>{$message}</div>
    </div>
</div>
HTML;
    }

    private function renderSpacer(array $attrs, array $styles): string
    {
        $height = match ($attrs['height'] ?? 'md') {
            'sm'    => 'h-6',
            'lg'    => 'h-16',
            'xl'    => 'h-24',
            default => 'h-10',
        };

        $showDivider = !empty($attrs['show_divider']);
        $customClass = Security::e((string)($styles['class'] ?? ''));

        if ($showDivider) {
            return <<<HTML
<div class="{$height} flex items-center justify-center {$customClass}">
    <div class="w-full border-t border-[#1e293b]"></div>
</div>
HTML;
        }

        return "<div class=\"{$height} {$customClass}\"></div>";
    }

    /**
     * Integrated Popup, Notification & Paywall Toolkit
     */
    private function renderPopup(array $attrs, array $styles): string
    {
        $title = Security::e((string)($attrs['title'] ?? 'Mitteilung'));
        $message = Security::e((string)($attrs['message'] ?? ''));
        $trigger = (string)($attrs['trigger'] ?? 'time_delay');
        $triggerVal = (int)($attrs['trigger_value'] ?? 5);
        $ctaText = Security::e((string)($attrs['cta_text'] ?? 'Mehr erfahren'));
        $ctaUrl = Security::e((string)($attrs['cta_url'] ?? '#'));
        $requiredRole = (string)($attrs['required_role'] ?? 'member');
        $variant = (string)($attrs['variant'] ?? 'modal');

        // Condition Check: Role-Gate (Paywall)
        if ($trigger === 'role_gate') {
            $isLoggedIn = Auth::check();
            $hasAccess = $isLoggedIn && (Auth::hasRole($requiredRole) || Auth::isSuperAdmin());

            if ($hasAccess) {
                // User has required role: paywall unlocked, render nothing or small badge
                return "<!-- Paywall Unlocked for role: {$requiredRole} -->";
            }

            // Paywall locked: Render elegant locked barrier card
            return <<<HTML
<div class="my-10 p-8 rounded-2xl bg-gradient-to-b from-[#0f172a] to-[#090d16] border shadow-2xl text-center max-w-xl mx-auto" style="border-color: color-mix(in srgb, var(--hb-primary) 30%, transparent);">
    <div class="w-12 h-12 rounded-2xl text-hb-primary mx-auto mb-4 flex items-center justify-center text-xl font-bold" style="background-color: color-mix(in srgb, var(--hb-primary) 10%, transparent); color: var(--hb-primary);">
        🔒
    </div>
    <h3 class="text-xl font-black text-white tracking-tight mb-2">{$title}</h3>
    <p class="text-xs sm:text-sm text-slate-400 leading-relaxed mb-6">{$message}</p>
    <a href="{$ctaUrl}" class="inline-flex items-center space-x-2 px-6 py-3 rounded-xl bg-hb-primary bg-hb-primary-hover text-white font-semibold text-xs transition shadow-lg">
        <span>{$ctaText}</span>
        <span>&rarr;</span>
    </a>
</div>
HTML;
        }

        // Alpine.js Condition Triggers for modal/banner:
        // 'time_delay', 'scroll_depth', 'exit_intent'
        $triggerJs = match ($trigger) {
            'scroll_depth' => "window.addEventListener('scroll', () => { if (!dismissed && (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight) * 100) >= {$triggerVal}) show = true; });",
            'exit_intent'  => "document.addEventListener('mouseleave', (e) => { if (!dismissed && e.clientY <= 15) show = true; });",
            default        => "setTimeout(() => { if (!dismissed) show = true; }, {$triggerVal} * 1000);",
        };

        if ($variant === 'banner_bottom') {
            return <<<HTML
<div x-data="{ show: false, dismissed: false }"
     x-init="{$triggerJs}"
     x-show="show && !dismissed"
     x-cloak
     class="fixed bottom-5 left-5 right-5 sm:left-auto sm:right-5 max-w-md p-5 bg-[#0f172a] border rounded-2xl shadow-2xl z-50 transition transform duration-300"
     style="border-color: color-mix(in srgb, var(--hb-primary) 40%, transparent);">
    <div class="flex items-start justify-between">
        <h4 class="text-sm font-bold text-white">{$title}</h4>
        <button @click="dismissed = true" class="text-slate-400 hover:text-white transition">&times;</button>
    </div>
    <p class="text-xs text-slate-400 mt-1 mb-4 leading-relaxed">{$message}</p>
    <div class="flex items-center space-x-3">
        <a href="{$ctaUrl}" class="px-4 py-2 rounded-xl bg-hb-primary bg-hb-primary-hover text-white font-semibold text-xs transition">
            {$ctaText}
        </a>
        <button @click="dismissed = true" class="px-3 py-2 rounded-xl text-xs text-slate-400 hover:text-white transition">
            Später
        </button>
    </div>
</div>
HTML;
        }

        // Modal Dialog Variant
        return <<<HTML
<div x-data="{ show: false, dismissed: false }"
     x-init="{$triggerJs}"
     x-show="show && !dismissed"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
    <div @click.away="dismissed = true"
         class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 sm:p-8 max-w-md w-full shadow-2xl text-center transform transition-all">
        <div class="w-12 h-12 rounded-2xl text-hb-primary mx-auto mb-4 flex items-center justify-center text-xl font-bold" style="background-color: color-mix(in srgb, var(--hb-primary) 10%, transparent); color: var(--hb-primary);">
            ⚡
        </div>
        <h3 class="text-xl font-bold text-white mb-2">{$title}</h3>
        <p class="text-xs sm:text-sm text-slate-400 leading-relaxed mb-6">{$message}</p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="{$ctaUrl}" class="w-full sm:w-auto px-6 py-3 rounded-xl bg-hb-primary bg-hb-primary-hover text-white font-semibold text-xs transition shadow-lg">
                {$ctaText}
            </a>
            <button @click="dismissed = true" class="w-full sm:w-auto px-5 py-3 rounded-xl bg-[#1e293b] hover:bg-[#334155] text-slate-300 font-medium text-xs transition">
                Schließen
            </button>
        </div>
    </div>
</div>
HTML;
    }

    private function renderCustomBlock(string $type, array $attrs, array $styles, array $innerBlocks, array $context): string
    {
        $safeType = Security::e($type);
        return "<!-- Custom block [{$safeType}] -->";
    }
}

