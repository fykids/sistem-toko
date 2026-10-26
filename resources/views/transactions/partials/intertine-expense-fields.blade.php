@php($expenseInput = old('intertine_expenses', $transaction?->intertine_expenses ?? []))
@php($expenseItems = collect(is_array($expenseInput) ? $expenseInput : [])->filter(fn($expense) => is_array($expense))->values())
@php($intertineCategories = ['KR Jual', 'OP Survei', 'Fee CMO'])
@php($intertineCategoryCosts = $expenseItems->filter(fn(array $expense) => in_array(strtolower(trim($expense['name'] ?? '')), array_map('strtolower', $intertineCategories), true))->mapWithKeys(fn(array $expense) => [strtolower(trim($expense['name'])) => $expense['cost'] ?? null]))
@php($legacyIntertineExpenses = $expenseItems->reject(fn(array $expense) => in_array(strtolower(trim($expense['name'] ?? '')), array_map('strtolower', $intertineCategories), true))->values())
<div id="intertine-expenses" class="intertine-expense-fields {{ $expenseGridClass ?? '' }}">
    @foreach ($intertineCategories as $index => $category)
        @php($categoryKey = strtolower($category))
        <div class="form-group intertine-expense-item">
            <input type="hidden" name="intertine_expenses[{{ $index }}][name]" value="{{ $category }}">
            <label for="intertine-expense-cost-{{ $index }}">{{ $category }}</label>
            <input id="intertine-expense-cost-{{ $index }}" type="text"
                name="intertine_expenses[{{ $index }}][cost]"
                value="{{ old("intertine_expenses.{$index}.cost", $intertineCategoryCosts->get($categoryKey)) }}"
                placeholder="Rp 0"
                inputmode="decimal" data-rupiah-input>
        </div>
    @endforeach
    @if ($showLegacyIntertineExpenses ?? true)
        @foreach ($legacyIntertineExpenses as $index => $expense)
            <input type="hidden" name="intertine_expenses[{{ $index + count($intertineCategories) }}][name]"
                value="{{ $expense['name'] ?? '' }}">
            <input type="hidden" name="intertine_expenses[{{ $index + count($intertineCategories) }}][cost]"
                value="{{ $expense['cost'] ?? 0 }}" data-rupiah-input>
        @endforeach
    @endif
</div>
