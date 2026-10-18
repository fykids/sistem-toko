<template id="repair-item-template">
    <div class="repair-form-row repeatable-item">
        <div class="form-group"><label class="sr-only" for="repair-name-__INDEX__">Nama Perbaikan</label><input
                id="repair-name-__INDEX__" type="text" name="additional_repairs[__INDEX__][name]" required
                placeholder="Nama perbaikan" maxlength="100"></div>
        <div class="form-group"><label class="sr-only" for="repair-cost-__INDEX__">Biaya perbaikan dalam
                rupiah</label><input id="repair-cost-__INDEX__" class="text-right" type="text"
                name="additional_repairs[__INDEX__][cost]" placeholder="Rp 0" inputmode="decimal"
                data-rupiah-input required></div>
        <div class="form-group"><label class="sr-only" for="repair-date-__INDEX__">Tanggal perbaikan</label><input
                id="repair-date-__INDEX__" type="date" name="additional_repairs[__INDEX__][date]" required></div>
        <div class="form-group"><label class="sr-only" for="repair-status-__INDEX__">Status pembayaran
                perbaikan</label><select id="repair-status-__INDEX__"
                name="additional_repairs[__INDEX__][payment_status]" class="item-payment-status" required>
                <option value="">Pilih status</option>
                <option value="lunas">Lunas</option>
                <option value="belum_lunas">Belum lunas</option>
                <option value="panjar">Panjar</option>
                <option value="lainnya">Lainnya</option>
            </select></div>
        <div class="repair-row-actions">
            <button type="button" class="icon-button remove-item" aria-label="Hapus biaya perbaikan"
                title="Hapus biaya perbaikan">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 6h18M8 6V4h8v2m3 0-.9 14H5.9L5 6m4 4v6m6-6v6" />
                </svg>
            </button>
        </div>
    </div>
</template>

<template id="tax-item-template">
    <div class="repair-group tax-item repeatable-item">
        <h5>Item Pajak</h5>
        <div class="form-group"><label>Nama Pajak</label><input type="text" name="tax_items[__INDEX__][name]"
                placeholder="Nama pajak" required maxlength="100"></div>
        <div class="form-group"><label>Tanggal Pajak</label><input type="date" name="tax_items[__INDEX__][date]"
                required></div>
        <div class="form-group"><label>Biaya (Rp)</label><input type="text" name="tax_items[__INDEX__][cost]"
                placeholder="Rp 0" inputmode="decimal" data-rupiah-input required></div>
        <div class="form-group"><label>Status Pembayaran</label><select name="tax_items[__INDEX__][payment_status]"
                class="item-payment-status" required>
                <option value="">Pilih status</option>
                <option value="lunas">Lunas</option>
                <option value="belum_lunas">Belum lunas</option>
                <option value="panjar">Panjar</option>
                <option value="lainnya">Lainnya</option>
            </select></div>
        <div class="form-group item-payment-note"><label>Keterangan</label><input type="text"
                name="tax_items[__INDEX__][payment_note]" placeholder="Keterangan tambahan" maxlength="255"></div>
        <button type="button" class="btn remove-item">Hapus</button>
    </div>
</template>
