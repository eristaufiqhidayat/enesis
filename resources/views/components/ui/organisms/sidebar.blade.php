@php($current = request()->route('slug') ?? request()->path())
@php($searchMenus = \App\Support\Akses\Akses::menuPencarian(auth()->user()))
@php($hasActiveDescendant = function (array $items) use (&$hasActiveDescendant, $current) {
    foreach ($items as $item) {
        if (isset($item['children']) ? $hasActiveDescendant($item['children']) : $item['slug'] === $current) {
            return true;
        }
    }

    return false;
})
<nav class="sidebar">
    <div class="brand">
        <div class="brand-actions">
            <button type="button" data-sidebar-toggle aria-label="Persempit/lebarkan sidebar"><x-ui.atoms.icon name="menu" /></button>
        </div>
        <div class="sidebar-search">
            <x-ui.atoms.input type="search" data-sidebar-search placeholder="Cari menu..." autocomplete="off" />
            <ul data-sidebar-search-results class="sidebar-search-results hidden"></ul>
        </div>
    </div>
    <div class="sidebar-scroll">
    <ul class="nav flex flex-col list-none m-0 p-0">
        @foreach (\App\Support\Akses\Akses::menu(auth()->user()) as $section)
            @if ($section['label'])
                {{-- Judul grup adalah flyout seperti submenu: isi grup muncul di kanan judul (desktop) atau accordion inline (mobile). --}}
                @php($open = $hasActiveDescendant($section['items']))
                <li class="nav-item has-flyout section-group">
                    <div class="section-label {{ $open ? 'is-open has-active-child' : '' }}" data-flyout-toggle role="button" tabindex="0" aria-haspopup="true" aria-expanded="{{ $open ? 'true' : 'false' }}">
                        <span class="section-label-text">
                            <x-ui.atoms.icon :name="$section['icon']" class="section-label-icon" />
                            <span class="section-label-name">{{ $section['label'] }}</span>
                        </span>
                        <x-ui.atoms.icon name="chevron-right" class="nav-chevron" />
                    </div>
                    <span class="nav-flyout-arrow" data-flyout-arrow aria-hidden="true"></span>
                    <ul class="nav flex flex-col list-none m-0 p-0 nav-flyout {{ $open ? 'is-open' : '' }}" data-flyout-panel>
                        <li class="nav-flyout-header">
                            <x-ui.atoms.icon :name="$section['icon']" class="section-label-icon" />
                            <span>{{ $section['label'] }}</span>
                        </li>
                        <x-ui.organisms.partials.sidebar-nav-items :items="$section['items']" :current="$current" />
                    </ul>
                </li>
            @else
                <ul class="flex flex-col list-none m-0 p-0">
                    <x-ui.organisms.partials.sidebar-nav-items :items="$section['items']" :current="$current" :top-level="true" />
                </ul>
            @endif
        @endforeach
    </ul>
    </div>
</nav>
<script>
    (() => {
        const searchMenus = @json($searchMenus);

        // Pencarian menu: filter judul (substring, case-insensitive), maksimal 8 hasil di dropdown.
        const SEARCH_LIMIT = 8;
        const searchInput = document.querySelector('[data-sidebar-search]');
        const searchResults = document.querySelector('[data-sidebar-search-results]');

        const hideSearchResults = () => searchResults.classList.add('hidden');

        const renderSearchResults = () => {
            const query = searchInput.value.trim().toLowerCase();
            searchResults.replaceChildren();
            if (! query) {
                hideSearchResults();
                return;
            }

            const matches = searchMenus
                .filter((menu) => menu.title.toLowerCase().includes(query))
                .slice(0, SEARCH_LIMIT);

            if (matches.length === 0) {
                const empty = document.createElement('li');
                empty.className = 'sidebar-search-empty';
                empty.textContent = 'Tidak ditemukan';
                searchResults.append(empty);
            }

            matches.forEach((menu) => {
                const link = document.createElement('a');
                link.href = `/${menu.slug}`;
                link.textContent = menu.title;

                const item = document.createElement('li');
                item.setAttribute('data-search-item', '');
                item.append(link);
                searchResults.append(item);
            });

            // Hasil baru → sorot item pertama, supaya Enter langsung membuka hasil teratas.
            activeSearchIndex = 0;
            highlightSearchItem();
            searchResults.classList.remove('hidden');
        };

        const searchItems = () => [...searchResults.querySelectorAll('[data-search-item]')];
        let activeSearchIndex = 0;

        const highlightSearchItem = () => {
            const items = searchItems();
            items.forEach((item, index) => item.classList.toggle('is-active', index === activeSearchIndex));
            items[activeSearchIndex]?.scrollIntoView({ block: 'nearest' });
        };

        searchInput.addEventListener('input', renderSearchResults);
        searchInput.addEventListener('focus', renderSearchResults);
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                // preventDefault mencegah Chrome mengosongkan input type="search" saat Escape.
                e.preventDefault();
                hideSearchResults();
                return;
            }

            const items = searchItems();
            if (items.length === 0) return;

            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (searchResults.classList.contains('hidden')) {
                    searchResults.classList.remove('hidden');
                    return;
                }
                const step = e.key === 'ArrowDown' ? 1 : -1;
                activeSearchIndex = (activeSearchIndex + step + items.length) % items.length;
                highlightSearchItem();
            }

            if (e.key === 'Enter' && !searchResults.classList.contains('hidden')) {
                e.preventDefault();
                items[activeSearchIndex].querySelector('a').click();
            }
        });

        // Klik di luar area pencarian menutup dropdown tanpa mengosongkan input.
        document.addEventListener('click', (e) => {
            if (! e.target.closest('.sidebar-search')) hideSearchResults();
        });

        // Tooltip nama menu grup tanpa label saat sidebar dipersempit. Dirender fixed di body karena .sidebar memotong overflow.
        const menuTooltip = document.createElement('div');
        menuTooltip.className = 'tooltip-wrapper tooltip-right tooltip-floating';

        document.querySelectorAll('.sidebar a[data-tooltip-menu]').forEach((link) => {
            link.addEventListener('mouseenter', () => {
                if (! desktopQuery.matches || ! document.body.classList.contains('sidebar-collapsed')) return;
                const rect = link.getBoundingClientRect();
                menuTooltip.setAttribute('data-tooltip', link.dataset.tooltipMenu);
                menuTooltip.style.left = `${rect.right + 6}px`;
                menuTooltip.style.top = `${rect.top + rect.height / 2}px`;
                document.body.append(menuTooltip);
            });

            link.addEventListener('mouseleave', () => menuTooltip.remove());
        });

        // Submenu (item ber-children): desktop memakai flyout ke kanan parent, mobile (≤991px) tetap accordion inline.
        const desktopQuery = window.matchMedia('(min-width: 992px)');
        const FLYOUT_CLOSE_DELAY = 150;
        const closeTimers = new WeakMap();

        const flyoutItems = (container) => [...container.children].filter((child) => child.matches('.has-flyout'));
        const flyoutPanel = (item) => item.querySelector(':scope > [data-flyout-panel]');
        const flyoutArrow = (item) => item.querySelector(':scope > [data-flyout-arrow]');

        // Panel di-fixed-kan (bukan absolute) karena .sidebar-scroll memakai overflow:hidden/auto dan akan memotongnya.
        // Panah (elemen terpisah, lihat app.css) diposisikan bersamaan: nempel di tepi kiri panel, sejajar baris pertama panel.
        const placeFlyout = (item) => {
            const toggle = item.querySelector(':scope > [data-flyout-toggle]');
            const panel = flyoutPanel(item);
            const arrow = flyoutArrow(item);
            const rect = toggle.getBoundingClientRect();
            const top = Math.max(8, Math.min(rect.top, window.innerHeight - panel.offsetHeight - 8));
            panel.style.left = `${rect.right + 4}px`;
            panel.style.top = `${top}px`;
            arrow.style.left = `${rect.right - 2}px`;
            arrow.style.top = `${top + 14}px`;
        };

        const setFlyoutState = (item, open) => {
            item.classList.toggle('is-flyout-open', open);
            item.querySelector(':scope > [data-flyout-toggle]').setAttribute('aria-expanded', open ? 'true' : 'false');
        };

        const closeFlyout = (item) => {
            clearTimeout(closeTimers.get(item));
            item.querySelectorAll('.is-flyout-open').forEach((open) => setFlyoutState(open, false));
            setFlyoutState(item, false);
        };

        const openFlyout = (item) => {
            // Flyout sibling di level yang sama ditutup dulu: hanya satu terbuka per level.
            flyoutItems(item.parentElement)
                .filter((sibling) => sibling !== item)
                .forEach(closeFlyout);

            clearTimeout(closeTimers.get(item));
            setFlyoutState(item, true);
            placeFlyout(item);
        };

        const repositionOpenFlyouts = () => {
            document.querySelectorAll('.sidebar .has-flyout.is-flyout-open').forEach(placeFlyout);
        };

        const scheduleCloseFlyout = (item) => {
            closeTimers.set(item, setTimeout(() => closeFlyout(item), FLYOUT_CLOSE_DELAY));
        };

        const toggleAccordion = (item) => {
            const panel = flyoutPanel(item);
            const toggle = item.querySelector(':scope > [data-flyout-toggle]');
            const expanded = panel.classList.toggle('is-open');
            toggle.classList.toggle('is-open', expanded);
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        };

        document.querySelectorAll('.sidebar .has-flyout').forEach((item) => {
            const toggle = item.querySelector(':scope > [data-flyout-toggle]');

            item.addEventListener('mouseenter', () => {
                if (desktopQuery.matches) openFlyout(item);
            });

            item.addEventListener('mouseleave', () => {
                if (desktopQuery.matches) scheduleCloseFlyout(item);
            });

            toggle.addEventListener('click', (e) => {
                e.preventDefault();

                if (! desktopQuery.matches) {
                    toggleAccordion(item);
                    return;
                }

                // Sidebar yang dipersempit tetap dipersempit saat submenu diklik; flyout tetap terbuka di strip ikon.
                // Klik membuka (tidak membalik), supaya klik setelah hover tidak menutup flyout yang sudah terbuka.
                openFlyout(item);
            });

            // Enter pada <a> sudah memicu click bawaan; keydown yang di-preventDefault mencegahnya, jadi tidak ada dobel.
            toggle.addEventListener('keydown', (e) => {
                if (e.key !== ' ' && e.key !== 'Enter') return;
                e.preventDefault();
                toggle.click();
            });
        });

        // Klik di luar parent dan panel-nya menutup flyout yang terbuka.
        document.addEventListener('click', (e) => {
            document.querySelectorAll('.sidebar .has-flyout.is-flyout-open').forEach((item) => {
                if (! item.contains(e.target)) closeFlyout(item);
            });
        });

        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            document.querySelectorAll('.sidebar .has-flyout.is-flyout-open').forEach(closeFlyout);
        });

        // Flyout memakai posisi fixed dari saat dibuka, jadi ditutup saat sidebar di-scroll agar tidak lepas dari parent-nya.
        document.querySelector('.sidebar-scroll')?.addEventListener('scroll', () => {
            document.querySelectorAll('.sidebar .has-flyout.is-flyout-open').forEach(closeFlyout);
        });

        // Lebar sidebar beranimasi (lihat .sidebar transition), jadi posisi flyout dihitung ulang setelah transisi selesai.
        document.querySelector('.sidebar').addEventListener('transitionend', (e) => {
            if (e.propertyName === 'width') repositionOpenFlyouts();
        });

        // Lewat breakpoint mobile saat flyout terbuka: tutup, supaya tidak muncul lagi di posisi lama saat lebar kembali.
        desktopQuery.addEventListener('change', (e) => {
            if (! e.matches) document.querySelectorAll('.sidebar .has-flyout.is-flyout-open').forEach(closeFlyout);
        });
    })();
</script>
