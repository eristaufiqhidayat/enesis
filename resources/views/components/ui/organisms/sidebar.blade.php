@php($current = request()->route('slug') ?? request()->path())
<nav class="sidebar">
    <div class="brand"><i class="fas fa-leaf me-1"></i> {{ config('app.name') }}</div>
    <ul class="nav flex flex-col list-none m-0 p-0">
        @foreach (config('marketama.menu') as $section)
            @php($groupId = 'group-' . $loop->index)
            @if ($section['label'])
                @php($groupSlugs = collect($section['items'])->flatMap(fn ($item) => isset($item['children']) ? array_column($item['children'], 'slug') : [$item['slug']])->implode(','))
                <li class="section-label is-open" data-collapse-toggle="{{ $groupId }}" data-group-label="{{ $section['label'] }}" role="button" tabindex="0" aria-expanded="true">
                    {{ $section['label'] }}
                    <i class="fas fa-chevron-down nav-chevron"></i>
                </li>
            @endif
            <ul @if ($section['label']) id="{{ $groupId }}" data-group-slugs="{{ $groupSlugs }}" @endif class="flex flex-col list-none m-0 p-0">
                <x-ui.organisms.partials.sidebar-nav-items :items="$section['items']" :current="$current" />
            </ul>
        @endforeach
    </ul>
</nav>
<script>
    // Collapse per grup sidebar: state disimpan di localStorage supaya tetap
    // terbaca setelah reload maupun saat pindah halaman (full reload Blade).
    (() => {
        const STORAGE_KEY = 'sidebar-collapsed-groups';
        const currentSlug = @json($current);

        const readCollapsed = () => {
            try {
                const saved = JSON.parse(localStorage.getItem(STORAGE_KEY));
                return Array.isArray(saved) ? saved : [];
            } catch {
                return [];
            }
        };

        const writeCollapsed = (labels) => {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(labels));
            } catch {
                // localStorage tidak tersedia (mode privat dll.), state collapse tidak persist.
            }
        };

        document.querySelectorAll('.sidebar .section-label[data-collapse-toggle]').forEach((toggle) => {
            const label = toggle.dataset.groupLabel;
            const target = document.getElementById(toggle.dataset.collapseToggle);
            if (!target) return;

            const groupSlugs = (target.dataset.groupSlugs || '').split(',');
            const hasActiveMenu = groupSlugs.includes(currentSlug);
            let collapsed = readCollapsed().includes(label) && !hasActiveMenu;

            target.classList.toggle('hidden', collapsed);
            toggle.classList.toggle('is-open', !collapsed);
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');

            // Klik ditangani oleh script generic [data-collapse-toggle] di layouts/app.blade.php
            // (toggle class hidden/is-open pada target) — di sini kita cuma mencerminkan
            // state yang sama (bukan membaca classList setelahnya) supaya tidak tergantung
            // urutan eksekusi antar script.
            toggle.addEventListener('click', () => {
                collapsed = !collapsed;
                toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');

                const labels = readCollapsed().filter((item) => item !== label);
                if (collapsed) labels.push(label);
                writeCollapsed(labels);
            });

            toggle.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                e.preventDefault();
                toggle.click();
            });
        });
    })();
</script>
