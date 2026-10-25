// Sub-grup di section footer (Master Data & Pengaturan) yang memuat slug aktif, atau null bila slug bukan anak sub-grup itu
// (Panduan, Pengumuman, halaman turunan seperti master_area/design_area). Dipakai SettingsSideNav dan AppLayout.
export function cariSubGrup(menu, currentSlug) {
    if (!currentSlug) return null;

    for (const section of menu ?? []) {
        if (section.posisi !== 'footer') continue;

        const subGrup = (section.items ?? []).find((item) => item.children?.some((anak) => anak.slug === currentSlug));
        if (subGrup) return subGrup;
    }

    return null;
}
