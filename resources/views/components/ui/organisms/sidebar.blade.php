@php($current = request()->route('slug') ?? request()->path())
<nav class="sidebar">
    <div class="brand">
        <div class="brand-actions">
            <button type="button" data-toggle-all-groups aria-label="Buka/tutup semua grup" title="Buka/tutup semua grup">
                <x-ui.atoms.icon name="chevrons-up" data-toggle-all-icon="up" />
                <x-ui.atoms.icon name="chevrons-down" data-toggle-all-icon="down" class="hidden" />
            </button>
            <button type="button" data-sidebar-toggle aria-label="Persempit/lebarkan sidebar"><x-ui.atoms.icon name="menu" /></button>
        </div>
    </div>
    <div class="sidebar-scroll">
    <ul class="nav flex flex-col list-none m-0 p-0">
        @foreach (\App\Support\Akses\Akses::menu(auth()->user()) as $section)
            @php($groupId = 'group-' . $loop->index)
            @if ($section['label'])
                @php($groupSlugs = collect($section['items'])->flatMap(fn ($item) => isset($item['children']) ? array_column($item['children'], 'slug') : [$item['slug']])->implode(','))
                <li class="section-label is-open" data-collapse-toggle="{{ $groupId }}" data-group-label="{{ $section['label'] }}" role="button" tabindex="0" aria-expanded="true">
                    {{ $section['label'] }}
                    <x-ui.atoms.icon name="chevron-down" class="nav-chevron" />
                </li>
            @endif
            <ul @if ($section['label']) id="{{ $groupId }}" data-group-slugs="{{ $groupSlugs }}" @endif class="flex flex-col list-none m-0 p-0">
                <x-ui.organisms.partials.sidebar-nav-items :items="$section['items']" :current="$current" />
            </ul>
        @endforeach
    </ul>
    </div>
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

        const isCollapsed = (group, saved) => saved.includes(group.label);


        const applyGroup = (group, collapsed) => {
            group.target.classList.toggle('hidden', collapsed);
            group.toggle.classList.toggle('is-open', !collapsed);
            group.toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        };

        const groups = [];

        document.querySelectorAll('.sidebar .section-label[data-collapse-toggle]').forEach((toggle) => {
            const label = toggle.dataset.groupLabel;
            const target = document.getElementById(toggle.dataset.collapseToggle);
            if (!target) return;

            const groupSlugs = (target.dataset.groupSlugs || '').split(',');
            const group = { label, toggle, target, hasActiveMenu: groupSlugs.includes(currentSlug) };
            groups.push(group);

            applyGroup(group, isCollapsed(group, readCollapsed()));

            // Klik ditangani juga oleh script generic [data-collapse-toggle] di layouts/app.blade.php,
            // yang membalik class hidden pada target SETELAH handler ini (script sidebar dirender lebih dulu).
            // Karena itu arah klik dibaca dari target sebelum dibalik, bukan dari variabel lokal.
            toggle.addEventListener('click', () => {
                const collapsed = !target.classList.contains('hidden');
                toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');

                const labels = readCollapsed().filter((item) => item !== label);
                if (collapsed) labels.push(label);
                writeCollapsed(labels);
                // Status DOM baru belum final di sini (handler generic belum jalan), jadi tombol "semua grup" diperbarui setelah klik selesai.
                setTimeout(updateToggleAll);

                toggle.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });

            toggle.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                e.preventDefault();
                toggle.click();
            });
        });

        const toggleAll = document.querySelector('[data-toggle-all-groups]');

        // Ikon & label mengikuti aksi berikutnya, dihitung dari grup yang tertutup di DOM
        // (bukan dari state tersimpan, supaya grup aktif yang ditutup user juga terhitung).
        const updateToggleAll = () => {
            const anyCollapsed = groups.some((group) => group.target.classList.contains('hidden'));
            const label = anyCollapsed ? 'Buka semua grup' : 'Tutup semua grup';
            toggleAll.querySelector('[data-toggle-all-icon="up"]').classList.toggle('hidden', anyCollapsed);
            toggleAll.querySelector('[data-toggle-all-icon="down"]').classList.toggle('hidden', !anyCollapsed);
            toggleAll.setAttribute('aria-label', label);
            toggleAll.setAttribute('title', label);
        };

        // Ada grup tertutup → buka semua (hapus semua label). Semua terbuka → tutup semua
        // (isi dengan label grup yang tampil, kecuali grup berisi menu aktif yang tetap terbuka).
        toggleAll.addEventListener('click', () => {
            const expandAll = groups.some((group) => group.target.classList.contains('hidden'));
            writeCollapsed(expandAll ? [] : groups.filter((group) => !group.hasActiveMenu).map((group) => group.label));

            const saved = readCollapsed();
            groups.forEach((group) => applyGroup(group, isCollapsed(group, saved)));
            updateToggleAll();
        });

        updateToggleAll();
    })();
</script>
