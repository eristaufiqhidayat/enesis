// State form Depo → Provinsi → Kota/Kabupaten → Kecamatan yang dipakai bersama Generate Polygon Kelurahan
// dan Auto Grid. Opsi kota/kecamatan diambil dari endpoint opsi (tingkat=kota|kecamatan).
import { computed, reactive, watch } from 'vue';

export function useFormWilayah(props) {
    const form = reactive({ warehouse_id: '', provinsi_id: '', kota_id: '', kecamatan_id: '' });
    const opsi = reactive({ kota: [], kecamatan: [] });
    const opsiDepo = computed(() => props.depo.map((d) => ({ value: d.warehouse_id, label: d.name })));
    const opsiProvinsi = computed(() => props.provinsi.map((p) => ({ value: p.id, label: p.nama })));
    const depoTerpilih = computed(() => props.depo.find((d) => String(d.warehouse_id) === String(form.warehouse_id)) ?? null);
    const keOpsi = (daftar) => daftar.map((x) => ({ value: x.id, label: x.nama }));

    async function ambilOpsi(params) {
        const res = await fetch(props.opsiUrl + '?' + new URLSearchParams(params), { headers: { Accept: 'application/json' } });
        return res.ok ? res.json() : [];
    }

    // Depo terpilih mengisi provinsi otomatis bila nama provinsinya ada di master.
    watch(depoTerpilih, (depo) => {
        const prov = depo && props.provinsi.find((p) => String(p.nama ?? '').toLowerCase() === String(depo.province ?? '').toLowerCase());
        if (prov && String(form.provinsi_id) !== String(prov.id)) form.provinsi_id = prov.id;
    });

    // Respons lama dibuang bila induknya sudah diganti selama menunggu.
    watch(() => form.provinsi_id, async (id) => {
        form.kota_id = '';
        opsi.kota = [];
        if (!id) return;
        const hasil = await ambilOpsi({ tingkat: 'kota', provinsi_id: id });
        if (String(form.provinsi_id) === String(id)) opsi.kota = keOpsi(hasil);
    });

    watch(() => form.kota_id, async (id) => {
        form.kecamatan_id = '';
        opsi.kecamatan = [];
        if (!id) return;
        const hasil = await ambilOpsi({ tingkat: 'kecamatan', kota_id: id });
        if (String(form.kota_id) === String(id)) opsi.kecamatan = keOpsi(hasil);
    });

    return { form, opsi, opsiDepo, opsiProvinsi, depoTerpilih };
}
