// Kunci provide/inject: slug menu efektif halaman (breadcrumbSlug AppLayout, atau currentSlug),
// dipakai PageHeader untuk mencari ikon menu tanpa perlu prop `icon` manual di tiap halaman.
export const PAGE_HEADER_SLUG = Symbol('page-header-slug');
