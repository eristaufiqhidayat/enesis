/**
 * Cari ikon menu (Lucide) untuk slug tertentu dari config sidebar (prop `menu`, lihat config/marketama.php).
 * Item anak (children) tanpa ikon sendiri mewarisi ikon induknya (pola yang sama dipakai Breadcrumb.vue).
 */
export function cariIkonMenu(menu, slug) {
    if (!slug) {
        return null;
    }

    for (const section of menu ?? []) {
        for (const item of section.items ?? []) {
            if ((item.slug ?? null) === slug) {
                return item.icon ?? null;
            }

            for (const child of item.children ?? []) {
                if ((child.slug ?? null) === slug) {
                    return child.icon ?? item.icon ?? null;
                }
            }
        }
    }

    return null;
}
