<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/ui/atoms/Icon.vue';

// items: section "posisi: footer" dari config (sudah difilter hak akses oleh Akses::menu).
const props = defineProps({
    items: { type: Array, required: true },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const currentSlug = computed(() => page.props.currentSlug ?? '');

// Entri grup menuju anak pertama yang tersisa setelah filter izin (grup tanpa anak sudah dibuang di Akses::menu).
function slugPertama(item) {
    return item.children ? slugPertama(item.children[0]) : item.slug;
}

function adaAktif(item) {
    return item.children ? item.children.some(adaAktif) : item.slug === currentSlug.value;
}

const entri = computed(() =>
    props.items.map((item) => ({ ...item, href: `/${slugPertama(item)}`, aktif: adaAktif(item) })),
);

const grupAktif = computed(() => entri.value.some((item) => item.aktif));

const open = ref(false);
const wrapper = ref(null);

// Tooltip nama saat sidebar dipersempit (teks tombol disembunyikan di strip ikon). Dirender fixed di body,
// karena .sidebar memotong overflow. Tidak ditampilkan saat popup terbuka, supaya tidak menutupi popup.
const tip = ref(null);

function showTip(e) {
    if (open.value || !window.matchMedia('(min-width: 992px)').matches) return;
    if (!document.body.classList.contains('sidebar-collapsed')) return;
    const rect = e.currentTarget.getBoundingClientRect();
    tip.value = { title: user.value.name, left: rect.right + 6, top: rect.top + rect.height / 2 };
}

function hideTip() {
    tip.value = null;
}

function toggle() {
    open.value = !open.value;
    hideTip();
}

function closeOnOutsideClick(e) {
    if (wrapper.value && !wrapper.value.contains(e.target)) open.value = false;
}

onMounted(() => document.addEventListener('click', closeOnOutsideClick));
onUnmounted(() => document.removeEventListener('click', closeOnOutsideClick));

function keluar() {
    open.value = false;
    router.post('/logout');
}
</script>

<template>
    <div v-if="user" ref="wrapper" class="sidebar-user-menu" @keydown.esc="open = false">
        <button
            type="button"
            class="sidebar-user-btn"
            :class="{ 'is-active': grupAktif }"
            aria-haspopup="menu"
            :aria-expanded="open ? 'true' : 'false'"
            @click="toggle"
            @mouseenter="showTip"
            @mouseleave="hideTip"
        >
            <Icon name="circle-user" class="sidebar-user-avatar" />
            <span class="sidebar-user-text">
                <span class="sidebar-user-name">{{ user.name }}</span>
                <span class="sidebar-user-role">{{ user.role ?? 'Tanpa role' }}</span>
            </span>
        </button>
        <div class="sidebar-user-popup" :class="{ hidden: !open }" role="menu">
            <div class="sidebar-user-popup-header">
                <span class="sidebar-user-name">{{ user.name }}</span>
                <span class="sidebar-user-role">{{ user.role ?? 'Tanpa role' }}</span>
            </div>
            <ul class="sidebar-user-popup-list">
                <li v-for="item in entri" :key="item.id ?? item.slug">
                    <Link role="menuitem" :href="item.href" :class="{ 'is-active': item.aktif }" @click="open = false">
                        <Icon v-if="item.icon" :name="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </li>
                <li>
                    <Link role="menuitem" href="/ganti-password" @click="open = false">
                        <Icon name="key" />
                        <span>Ganti Password</span>
                    </Link>
                </li>
                <li>
                    <button type="button" role="menuitem" @click="keluar">
                        <Icon name="log-out" />
                        <span>Keluar</span>
                    </button>
                </li>
            </ul>
        </div>
        <Teleport to="body">
            <div v-if="tip" class="tooltip-wrapper tooltip-right tooltip-floating" :style="{ left: `${tip.left}px`, top: `${tip.top}px` }" :data-tooltip="tip.title"></div>
        </Teleport>
    </div>
</template>
