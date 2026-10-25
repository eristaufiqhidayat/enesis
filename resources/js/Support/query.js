// Buang parameter kosong (null, undefined, '') sebelum dijadikan query string, supaya URL tetap bersih.
export const bersihkanQuery = (query) => Object.fromEntries(
    Object.entries(query).filter(([, nilai]) => nilai !== null && nilai !== undefined && nilai !== ''),
);
