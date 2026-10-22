<section class="form-section costs-section">
    <header class="section-heading"><span class="section-number">02</span>
        <div>
        @can('viewSensitiveData', \App\Models\CarTransaction::class)
            <h2>Modal dan biaya kendaraan</h2>
        @else
            <h2>Tanggal beli dan biaya kendaraan</h2>
        @endcan
    </div>
    </header>
    <div class="section-content">
        @cannot('viewSensitiveData', \App\Models\CarTransaction::class)
        <input id="buy-price" type="hidden" name="buy_price" value="0" data-rupiah-input>
        @endcannot
        <div class="field-grid costs-primary-grid">
            @can('viewSensitiveData', \App\Models\CarTransaction::class)
                <div class="form-group buy-date-group"><label for="buy-price">Modal Beli (Rp) <span
                            aria-hidden="true">*</span></label>
                    <div class="cost-inputs cost-inputs--dated">
                        <input id="buy-price" type="text" name="buy_price"
                            value="{{ old('buy_price', $transaction?->buy_price) }}" placeholder="Rp 0"
                            inputmode="decimal" data-rupiah-input required>
                        <input id="buy-date" type="date" name="buy_date"
                            value="{{ old('buy_date', $transaction?->buy_date) }}" aria-label="Tanggal beli" required>
                    </div>
                </div>
            @else
                <div class="form-group buy-date-group"><label for="buy-date">Tanggal beli</label>
                    <div class="cost-inputs cost-inputs--date-only">
                        <input id="buy-date" type="date" name="buy_date"
                            value="{{ old('buy_date', $transaction?->buy_date) }}" required>
                    </div>
                </div>
            @endcan
        </div>
        @php($taxInput = old('tax_items', $transaction?->tax_items ?? []))
        @php($taxItemsForForm = collect(is_array($taxInput) ? $taxInput : [])->filter(fn($taxItem) => is_array($taxItem))->values())
        @php($taxItemsByName = $taxItemsForForm->keyBy(fn($taxItem) => strtolower($taxItem['name'] ?? '')))
        @php($primaryTaxItem = $taxItemsByName->get('pajak') ?? $taxItemsForForm->first())
        @php($otherTaxItems = $taxItemsForForm->reject(fn($taxItem) => $taxItem === $primaryTaxItem || in_array(strtolower($taxItem['name'] ?? ''), ['pajak', 'bbn', 'etle'], true))->values())
        <div class="field-grid costs-vertical-grid">
            <div class="form-group"><label for="purchase-operational">OPS (Rp)</label>
                <div class="cost-inputs">
                    <input id="purchase-operational" type="text" name="purchase_operational"
                        value="{{ old('purchase_operational', $transaction?->purchase_operational) }}"
                        placeholder="Rp 0" inputmode="decimal" data-rupiah-input>
                </div>
            </div>
            <div class="form-group"><label for="other-costs">KR Beli (Rp)</label>
                <div class="cost-inputs">
                    <input id="other-costs" type="text" name="other_costs"
                        value="{{ old('other_costs', $transaction?->other_costs) }}" placeholder="Rp 0"
                        inputmode="decimal" data-rupiah-input>
                </div>
            </div>
            <input type="hidden" name="tax_items[0][name]"
                value="{{ old('tax_items.0.name', data_get($primaryTaxItem, 'name', 'Pajak')) }}">
            <input type="hidden" name="tax_items[0][payment_status]"
                value="{{ old('tax_items.0.payment_status', data_get($primaryTaxItem, 'payment_status')) }}">
            <input type="hidden" name="tax_items[0][payment_note]"
                value="{{ old('tax_items.0.payment_note', data_get($primaryTaxItem, 'payment_note')) }}">
            <div class="form-group tax-cost-group"><label for="tax-price">Biaya Pajak</label>
                <div class="cost-inputs cost-inputs--dated">
                    <input id="tax-price" type="text" name="tax_items[0][cost]"
                        value="{{ old('tax_items.0.cost') ?? data_get($primaryTaxItem, 'cost') }}" placeholder="Rp 0"
                        inputmode="decimal" data-rupiah-input>
                    <input id="tax-date" type="date" name="tax_items[0][date]"
                        value="{{ old('tax_items.0.date', data_get($primaryTaxItem, 'date')) }}">
                </div>
            </div>
            <input type="hidden" name="tax_items[1][name]" value="BBN">
            <input type="hidden" name="tax_items[1][payment_status]"
                value="{{ old('tax_items.1.payment_status', data_get($taxItemsByName->get('bbn'), 'payment_status')) }}">
            <input type="hidden" name="tax_items[1][payment_note]"
                value="{{ old('tax_items.1.payment_note', data_get($taxItemsByName->get('bbn'), 'payment_note')) }}">
            <div class="form-group tax-cost-group"><label for="bbn-price">Biaya BBN</label>
                <div class="cost-inputs cost-inputs--dated">
                    <input id="bbn-price" type="text" name="tax_items[1][cost]"
                        value="{{ old('tax_items.1.cost', data_get($taxItemsByName->get('bbn'), 'cost')) }}"
                        placeholder="Rp 0" inputmode="decimal" data-rupiah-input>
                    <input id="bbn-date" type="date" name="tax_items[1][date]"
                        value="{{ old('tax_items.1.date', data_get($taxItemsByName->get('bbn'), 'date')) }}">
                </div>
            </div>
            <input type="hidden" name="tax_items[2][name]" value="ETLE">
            <input type="hidden" name="tax_items[2][payment_status]"
                value="{{ old('tax_items.2.payment_status', data_get($taxItemsByName->get('etle'), 'payment_status')) }}">
            <input type="hidden" name="tax_items[2][payment_note]"
                value="{{ old('tax_items.2.payment_note', data_get($taxItemsByName->get('etle'), 'payment_note')) }}">
            <div class="form-group tax-cost-group"><label for="etle-price">Biaya ETLE</label>
                <div class="cost-inputs">
                    <input id="etle-price" type="text" name="tax_items[2][cost]"
                        value="{{ old('tax_items.2.cost', data_get($taxItemsByName->get('etle'), 'cost')) }}"
                        placeholder="Rp 0" inputmode="decimal" data-rupiah-input>
                </div>
            </div>
            @foreach ($otherTaxItems as $index => $taxItem)
            @php($taxItemIndex = $index + 3)
            <input type="hidden" name="tax_items[{{ $taxItemIndex }}][name]" value="{{ $taxItem['name'] ?? '' }}">
            <input type="hidden" name="tax_items[{{ $taxItemIndex }}][date]"
                value="{{ old("tax_items.{$taxItemIndex}.date", $taxItem['date'] ?? '') }}">
            <input type="hidden" name="tax_items[{{ $taxItemIndex }}][cost]"
                value="{{ old("tax_items.{$taxItemIndex}.cost", $taxItem['cost'] ?? '') }}">
            <input type="hidden" name="tax_items[{{ $taxItemIndex }}][payment_status]"
                value="{{ old("tax_items.{$taxItemIndex}.payment_status", $taxItem['payment_status'] ?? '') }}">
            <input type="hidden" name="tax_items[{{ $taxItemIndex }}][payment_note]"
                value="{{ old("tax_items.{$taxItemIndex}.payment_note", $taxItem['payment_note'] ?? '') }}">
            @endforeach
        </div>
        @php($repairInput = old('additional_repairs', $transaction?->additional_repairs ?? []))
        @php($repairItems = collect(is_array($repairInput) ? $repairInput : [])->filter(fn($repair) => is_array($repair))->all())
        @if (empty($repairItems))
        @php($repairItems = [['name' => '', 'date' => '', 'cost' => '', 'payment_status' => '']])
        @endif
        <div id="additional-repairs" class="repair-form-list form-tabel"
            data-next-index="{{ (collect($repairItems)->keys()->max() ?? -1) + 1 }}">
            <div class="repair-list-actions">
                <span>Daftar biaya perbaikan</span>
                <button id="add-repair" type="button" class="btn btn-secondary btn-small repair-add-button">
                    <span aria-hidden="true">+</span>
                    Tambah perbaikan
                </button>
            </div>
            <div class="repair-form-labels" aria-hidden="true">
                <span>Nama Perbaikan</span>
                <span>Biaya (Rp)</span>
                <span>Tgl</span>
                <span>Status</span>
                <span class="repair-label-actions"></span>
            </div>
            @foreach ($repairItems as $index => $repair)
            @php($hasRepairData = collect($repair)->contains(fn($value) => filled($value)))
            <div class="repair-form-row repeatable-item">
                <div class="form-group"><label class="sr-only" for="repair-name-{{ $index }}">Nama
                        Perbaikan</label><input id="repair-name-{{ $index }}" type="text"
                        name="additional_repairs[{{ $index }}][name]" value="{{ $repair['name'] ?? '' }}"
                        placeholder="Nama perbaikan" @if ($hasRepairData) required @endif maxlength="100"></div>
                <div class="form-group"><label class="sr-only" for="repair-cost-{{ $index }}">Biaya perbaikan dalam
                        rupiah</label><input id="repair-cost-{{ $index }}" class="text-right" type="text"
                        name="additional_repairs[{{ $index }}][cost]" value="{{ $repair['cost'] ?? '' }}"
                        placeholder="Rp 0" inputmode="decimal" data-rupiah-input @if ($hasRepairData) required @endif></div>
                <div class="form-group"><label class="sr-only" for="repair-date-{{ $index }}">Tanggal
                        perbaikan</label><input id="repair-date-{{ $index }}" type="date"
                        name="additional_repairs[{{ $index }}][date]" value="{{ $repair['date'] ?? '' }}" @if ($hasRepairData) required @endif></div>
                <div class="form-group"><label class="sr-only" for="repair-status-{{ $index }}">Status pembayaran
                        perbaikan</label><select id="repair-status-{{ $index }}"
                        name="additional_repairs[{{ $index }}][payment_status]" class="item-payment-status" @if ($hasRepairData) required @endif>
                        <option value="">Pilih status</option>
                        <option value="lunas" @selected(($repair['payment_status'] ?? '') === 'lunas')>Lunas</option>
                        <option value="belum_lunas" @selected(($repair['payment_status'] ?? '') === 'belum_lunas')>
                            Belum lunas</option>
                        <option value="panjar" @selected(($repair['payment_status'] ?? '') === 'panjar')>Panjar
                        </option>
                        <option value="lainnya" @selected(($repair['payment_status'] ?? '') === 'lainnya')>Lainnya
                        </option>
                    </select></div>
                    <div class="repair-row-actions">
                        <button type="button" class="icon-button remove-item" aria-label="Hapus biaya perbaikan"
                            title="Hapus biaya perbaikan" @if (count($repairItems) === 1) hidden @endif>
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18M8 6V4h8v2m3 0-.9 14H5.9L5 6m4 4v6m6-6v6" />
                        </svg>
                    </button>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
