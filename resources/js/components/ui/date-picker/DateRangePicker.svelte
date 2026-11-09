<script lang="ts">
    import CalendarDays from '@lucide/svelte/icons/calendar-days';
    import ChevronLeft from '@lucide/svelte/icons/chevron-left';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import { CalendarDate, parseDate, today, type DateValue } from '@internationalized/date';
    import { Calendar, Popover, RangeCalendar, type DateRange, type Month } from 'bits-ui';

    type Mode = 'single' | 'range' | 'month' | 'year';

    let {
        id,
        value = $bindable(''),
        endValue = $bindable(''),
        mode = 'single',
        min,
        max,
        disabled = false,
        required = false,
        invalid = false,
        clearable = false,
    }: {
        id: string;
        value?: string;
        endValue?: string;
        mode?: Mode;
        min?: string;
        max?: string;
        disabled?: boolean;
        required?: boolean;
        invalid?: boolean;
        clearable?: boolean;
    } = $props();

    const uid = $props.id();
    const locale = 'id-ID';
    const dateFormatter = new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeZone: 'UTC' });
    const monthFormatter = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric', timeZone: 'UTC' });
    const monthNameFormatter = new Intl.DateTimeFormat(locale, { month: 'long', timeZone: 'UTC' });
    const monthNames = Array.from({ length: 12 }, (_, index) =>
        monthNameFormatter.format(new CalendarDate(2000, index + 1, 1).toDate('UTC')),
    );

    let open = $state(false);
    let trigger = $state<HTMLButtonElement | null>(null);
    let content = $state<HTMLDivElement | null>(null);
    let rangeDraft = $state.raw<DateRange>({ start: undefined, end: undefined });
    let year = $state(2000);
    let attempted = $state(false);

    const start = $derived(readDate(value, mode));
    const end = $derived(mode === 'range' ? readDate(endValue, mode) : undefined);
    const minDate = $derived(readDate(min, mode));
    const maxDate = $derived(readDate(max, mode));
    const minYear = $derived(minDate?.year ?? 1);
    const maxYear = $derived(maxDate?.year ?? 9999);
    const yearPageStart = $derived(Math.floor((year - 1) / 12) * 12 + 1);
    const yearChoices = $derived(Array.from({ length: Math.min(12, 10000 - yearPageStart) }, (_, index) => yearPageStart + index));
    const pickerLabel = $derived(
        mode === 'year' ? 'Pilih tahun' : mode === 'month' ? 'Pilih bulan' : mode === 'range' ? 'Pilih rentang tanggal' : 'Pilih tanggal',
    );
    const displayValue = $derived.by(() => {
        if (!start) return '';

        if (mode === 'year') return String(start.year).padStart(4, '0');
        if (mode === 'month') return monthFormatter.format(start.toDate('UTC'));

        const first = dateFormatter.format(start.toDate('UTC'));
        if (mode !== 'range') return first;

        return end ? first + ' – ' + dateFormatter.format(end.toDate('UTC')) : first + ' – …';
    });
    const empty = $derived(!value && (mode !== 'range' || !endValue));
    const complete = $derived(
        start !== undefined && withinBounds(start) &&
        (mode !== 'range' || (end !== undefined && withinBounds(end) && end.compare(start) >= 0)),
    );
    const valid = $derived(complete || (empty && !required));
    const validationError = $derived(
        attempted && !valid
            ? (empty ? pickerLabel + ' terlebih dahulu.' : 'Pilih tanggal atau periode dalam batas yang diizinkan.')
            : '',
    );

    const navClass = 'inline-flex size-9 shrink-0 pointer-coarse:size-11 items-center justify-center rounded-md border border-input bg-background hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-40';
    const dayClass = 'mx-auto flex h-9 w-full items-center justify-center rounded-md text-sm tabular-nums focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring data-[disabled]:text-muted-foreground data-[disabled]:opacity-40 data-[today]:font-semibold [&:not([data-disabled]):hover]:bg-accent [&:not([data-disabled]):hover]:text-accent-foreground';
    const singleDayClass = dayClass + ' data-[selected]:bg-primary data-[selected]:text-primary-foreground [&[data-selected]:hover]:bg-primary [&[data-selected]:hover]:text-primary-foreground';
    const rangeDayClass = dayClass + ' data-[highlighted]:bg-accent data-[range-middle]:bg-accent data-[selection-start]:bg-primary data-[selection-start]:text-primary-foreground data-[selection-end]:bg-primary data-[selection-end]:text-primary-foreground [&[data-selection-start]:hover]:bg-primary [&[data-selection-start]:hover]:text-primary-foreground [&[data-selection-end]:hover]:bg-primary [&[data-selection-end]:hover]:text-primary-foreground';

    function readDate(raw: string | undefined, dateMode: Mode): CalendarDate | undefined {
        if (!raw) return;

        const iso = dateMode === 'year' ? raw + '-01-01' : dateMode === 'month' ? raw + '-01' : raw;
        if (!/^\d{4}-\d{2}-\d{2}$/.test(iso) || iso.startsWith('0000-')) return;

        try {
            const date = parseDate(iso);
            return date.toString() === iso ? date : undefined;
        } catch {
            return undefined;
        }
    }

    function withinBounds(date: DateValue): boolean {
        return date.year <= 9999 && date.era === 'AD' &&
            (!minDate || date.compare(minDate) >= 0) &&
            (!maxDate || date.compare(maxDate) <= 0);
    }

    function onOpenChange(next: boolean) {
        if (!next) return;

        const initialStart = start && withinBounds(start) ? start : undefined;
        rangeDraft = {
            start: initialStart,
            end: initialStart && end && withinBounds(end) && end.compare(initialStart) >= 0 ? end : undefined,
        };
        year = Math.max(minYear, Math.min(maxYear, start?.year ?? today('Asia/Jakarta').year));
    }

    function focusCalendar(event: Event) {
        const target = content?.querySelector<HTMLElement>(
            mode === 'year' ? '[data-year-selected]:not(:disabled)'
                : mode === 'month' ? '[data-month-selected]:not(:disabled)'
                    : '[data-bits-day][tabindex="0"]',
        ) ?? content?.querySelector<HTMLElement>('[data-month-option]:not(:disabled), [data-year-option]:not(:disabled)');

        if (target) {
            event.preventDefault();
            target.focus();
        }
    }

    function chooseDate(date: DateValue | undefined) {
        if (!date || !withinBounds(date)) return;

        value = date.toString();
        attempted = false;
        open = false;
    }

    function chooseRange(range: DateRange) {
        if (!range.start || !range.end || !withinBounds(range.start) || !withinBounds(range.end)) return;

        value = range.start.toString();
        endValue = range.end.toString();
        attempted = false;
        open = false;
    }

    function chooseMonth(date: CalendarDate) {
        if (!withinBounds(date)) return;

        value = date.toString().slice(0, 7);
        attempted = false;
        open = false;
    }

    function chooseYear(selectedYear: number) {
        const date = new CalendarDate(selectedYear, 1, 1);
        if (!withinBounds(date)) return;

        value = String(selectedYear).padStart(4, '0');
        attempted = false;
        open = false;
    }

    function clear() {
        value = '';
        if (mode === 'range') endValue = '';
        attempted = false;
        open = false;
    }

    function showValidation(event: Event & { currentTarget: HTMLInputElement }) {
        event.preventDefault();
        attempted = true;

        const firstInvalid = event.currentTarget.form?.querySelector(':invalid');
        if (!firstInvalid || firstInvalid === event.currentTarget) {
            trigger?.focus();
        }
    }
</script>

{#snippet calendarGrid(months: Month<DateValue>[], weekdays: string[], range: boolean)}
    <Calendar.Header class="mb-2 flex items-center justify-between gap-1">
        <Calendar.PrevButton>
            {#snippet child({ props })}
                <button {...props} type="button" class={navClass} aria-label="Bulan sebelumnya">
                    <ChevronLeft class="size-4" aria-hidden="true" />
                </button>
            {/snippet}
        </Calendar.PrevButton>
        <Calendar.Heading class="text-center text-sm font-medium" />
        <Calendar.NextButton>
            {#snippet child({ props })}
                <button {...props} type="button" class={navClass} aria-label="Bulan berikutnya">
                    <ChevronRight class="size-4" aria-hidden="true" />
                </button>
            {/snippet}
        </Calendar.NextButton>
    </Calendar.Header>
    {#each months as month (month.value.toString())}
        <Calendar.Grid class="w-full table-fixed border-collapse">
            <Calendar.GridHead>
                <Calendar.GridRow>
                    {#each weekdays as weekday, index (index)}
                        <Calendar.HeadCell class="h-7 text-center text-xs font-medium text-muted-foreground">
                            {weekday}
                        </Calendar.HeadCell>
                    {/each}
                </Calendar.GridRow>
            </Calendar.GridHead>
            <Calendar.GridBody>
                {#each month.weeks as week (week[0].toString())}
                    <Calendar.GridRow>
                        {#each week as date (date.toString())}
                            {#if range}
                                <RangeCalendar.Cell {date} month={month.value} class="p-0">
                                    <RangeCalendar.Day class={rangeDayClass} />
                                </RangeCalendar.Cell>
                            {:else}
                                <Calendar.Cell {date} month={month.value} class="p-0">
                                    <Calendar.Day class={singleDayClass} />
                                </Calendar.Cell>
                            {/if}
                        {/each}
                    </Calendar.GridRow>
                {/each}
            </Calendar.GridBody>
        </Calendar.Grid>
    {/each}
{/snippet}

<Popover.Root bind:open {onOpenChange}>
    <Popover.Trigger
        {id}
        {disabled}
        bind:ref={trigger}
        aria-invalid={invalid || !!validationError}
        aria-describedby={uid + '-value' + (validationError ? ' ' + uid + '-error' : '')}
        class="flex min-h-9 w-full min-w-0 items-center gap-2 rounded-md border border-input bg-background px-3 py-1 text-left text-base shadow-sm sm:text-sm pointer-coarse:min-h-11 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive"
    >
        <CalendarDays class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
        <span id={uid + '-value'} class:text-muted-foreground={!displayValue}>{displayValue || pickerLabel}</span>
    </Popover.Trigger>

    <Popover.Portal>
        <Popover.Content
            align="start"
            role="dialog"
            sideOffset={6}
            collisionPadding={8}
            aria-label={pickerLabel}
            bind:ref={content}
            onOpenAutoFocus={focusCalendar}
            class="z-50 max-h-[var(--bits-popover-content-available-height)] w-72 max-w-[calc(100vw-1rem)] overflow-y-auto rounded-md border bg-popover p-3 text-popover-foreground shadow-md"
        >
            {#if mode === 'year'}
                <div class="mb-2 flex items-center justify-between gap-1">
                    <button type="button" class={navClass} aria-label="12 tahun sebelumnya" disabled={yearPageStart <= minYear} onclick={() => { year = Math.max(minYear, year - 12); }}>
                        <ChevronLeft class="size-4" aria-hidden="true" />
                    </button>
                    <span class="text-sm font-medium tabular-nums" aria-live="polite">{yearPageStart} – {yearChoices.at(-1)}</span>
                    <button type="button" class={navClass} aria-label="12 tahun berikutnya" disabled={(yearChoices.at(-1) ?? 9999) >= maxYear} onclick={() => { year = Math.min(maxYear, year + 12); }}>
                        <ChevronRight class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <div class="grid grid-cols-3 gap-1" role="group" aria-label="Pilih tahun laporan">
                    {#each yearChoices as item (item)}
                        <button type="button"
                            class="min-h-9 rounded-md px-1 text-sm tabular-nums hover:bg-accent pointer-coarse:min-h-11 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-40 aria-pressed:bg-primary aria-pressed:text-primary-foreground [&[aria-pressed=true]:hover]:bg-primary"
                            data-year-option data-year-selected={start?.year === item ? '' : undefined}
                            aria-pressed={start?.year === item}
                            disabled={item < minYear || item > maxYear}
                            onclick={() => chooseYear(item)}>{item}</button>
                    {/each}
                </div>
            {:else if mode === 'month'}
                <div class="mb-2 flex items-center justify-between gap-1">
                    <button type="button" class={navClass} aria-label="Tahun sebelumnya" disabled={year <= minYear} onclick={() => { year--; }}>
                        <ChevronLeft class="size-4" aria-hidden="true" />
                    </button>
                    <span class="text-sm font-medium tabular-nums" aria-live="polite">{year}</span>
                    <button type="button" class={navClass} aria-label="Tahun berikutnya" disabled={year >= maxYear} onclick={() => { year++; }}>
                        <ChevronRight class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <div class="grid grid-cols-3 gap-1" role="group" aria-label={'Bulan pada tahun ' + year}>
                    {#each monthNames as name, index (name)}
                        {@const date = new CalendarDate(year, index + 1, 1)}
                        {@const selected = start?.compare(date) === 0}
                        <button
                            type="button"
                            class="min-h-9 rounded-md px-1 text-sm hover:bg-accent pointer-coarse:min-h-11 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-40 aria-pressed:bg-primary aria-pressed:text-primary-foreground [&[aria-pressed=true]:hover]:bg-primary"
                            data-month-option
                            data-month-selected={selected ? '' : undefined}
                            aria-pressed={selected}
                            disabled={!withinBounds(date)}
                            onclick={() => chooseMonth(date)}
                        >{name}</button>
                    {/each}
                </div>
            {:else if mode === 'range'}
                <RangeCalendar.Root
                    bind:value={rangeDraft}
                    onValueChange={chooseRange}
                    minValue={minDate}
                    maxValue={maxDate}
                    {locale}
                    calendarLabel="Pilih rentang tanggal"
                    weekStartsOn={1}
                    weekdayFormat="short"
                    preventDeselect
                >
                    {#snippet children({ months, weekdays })}
                        {@render calendarGrid(months, weekdays, true)}
                    {/snippet}
                </RangeCalendar.Root>
                <p class="mt-2 text-xs text-muted-foreground" aria-live="polite">
                    {rangeDraft.start && !rangeDraft.end ? 'Pilih tanggal akhir.' : 'Pilih tanggal awal.'}
                </p>
            {:else}
                <Calendar.Root
                    type="single"
                    value={start && withinBounds(start) ? start : undefined}
                    onValueChange={chooseDate}
                    minValue={minDate}
                    maxValue={maxDate}
                    {locale}
                    calendarLabel="Pilih tanggal"
                    weekStartsOn={1}
                    weekdayFormat="short"
                    preventDeselect
                >
                    {#snippet children({ months, weekdays })}
                        {@render calendarGrid(months, weekdays, false)}
                    {/snippet}
                </Calendar.Root>
            {/if}

            <div class="mt-2 flex flex-wrap justify-end gap-2 border-t pt-2">
                {#if clearable && !empty}
                    <button type="button" class="min-h-8 rounded-md px-2 text-xs font-medium hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" onclick={clear}>
                        Hapus pilihan
                    </button>
                {/if}
                <Popover.Close class="min-h-8 rounded-md px-2 text-xs font-medium hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                    Tutup
                </Popover.Close>
            </div>
        </Popover.Content>
    </Popover.Portal>
</Popover.Root>

<input
    type="text"
    value={valid ? 'valid' : ''}
    class="sr-only"
    tabindex="-1"
    aria-hidden="true"
    required
    {disabled}
    oninvalid={showValidation}
/>
{#if validationError}
    <p id={uid + '-error'} class="text-sm text-destructive" role="alert">{validationError}</p>
{/if}
