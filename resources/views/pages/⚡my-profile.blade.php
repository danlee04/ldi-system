<?php

use App\Actions\Pds\PersonalDataSheetProgress;
use App\Enums\TrainingStatus;
use App\Models\Employee;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * What the system holds about the person signed in.
 *
 * Read-only on purpose. The employment details here are HR's to set, what
 * they may change themselves is on My PDS, and their sign-in details are
 * under Settings — this page points at both rather than duplicating them.
 */
new #[Title('My profile')] class extends Component {
    use WithFileUploads;

    /**
     * The picture being chosen, before it is saved.
     */
    public ?TemporaryUploadedFile $photo = null;

    public function mount(): void
    {
        abort_if($this->employee === null, 403, __('Your account is not linked to an employee record.'));
    }

    #[Computed(persist: true)]
    public function employee(): ?Employee
    {
        return auth()->user()->employee?->load(['section.division', 'position', 'eligibilities.eligibility']);
    }


    /**
     * The chosen file, if it is one a browser can actually show. Anything
     * else is about to fail validation and must not be asked for a URL —
     * Livewire throws on a file it cannot preview.
     */
    #[Computed]
    public function previewUrl(): ?string
    {
        return $this->photo?->isPreviewable() ? $this->photo->temporaryUrl() : null;
    }

    public function choosePhoto(): void
    {
        $this->reset('photo');
        $this->resetValidation();

        Flux::modal('profile-photo')->show();
    }

    public function savePhoto(): void
    {
        $this->validate([
            // The extension is never trusted: `image` reads the file, and
            // the stored name is generated rather than taken from it.
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=200,min_height=200'],
        ], [], ['photo' => __('photograph')]);

        $employee = $this->employee;
        $previous = $employee->photo_path;

        $employee->update([
            'photo_path' => $this->photo->store('employee-photos', 'public'),
        ]);

        $this->discard($previous);

        $this->reset('photo');
        unset($this->employee);

        Flux::modal('profile-photo')->close();

        $this->dispatch('photo-updated');

        Flux::toast(variant: 'success', text: __('Photograph saved.'));
    }

    public function removePhoto(): void
    {
        $employee = $this->employee;
        $previous = $employee->photo_path;

        $employee->update(['photo_path' => null]);

        $this->discard($previous);

        $this->reset('photo');
        unset($this->employee);

        Flux::modal('profile-photo')->close();

        $this->dispatch('photo-updated');

        Flux::toast(variant: 'success', text: __('Photograph removed. Your initials are back.'));
    }

    /**
     * A replaced picture is of no further use, so it does not sit on the
     * disk for the rest of the system's life.
     */
    private function discard(?string $path): void
    {
        if ($path !== null && $path !== $this->employee->photo_path) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * What their approved training counts for this year. Only approved
     * records count — a pending one may yet be turned down.
     */
    #[Computed]
    public function cpdUnits(): float
    {
        return (float) $this->employee->trainingRecords()
            ->where('status', TrainingStatus::Approved)
            ->whereYear('date_end', now()->year)
            ->sum('cpd_units');
    }

    /**
     * @return list<array{number: string, label: string, filled: bool}>
     */
    #[Computed]
    public function pdsSections(): array
    {
        return app(PersonalDataSheetProgress::class)->handle($this->employee);
    }

    #[Computed]
    public function pdsPercentage(): int
    {
        return app(PersonalDataSheetProgress::class)->percentage($this->pdsSections);
    }

    /**
     * @return list<array{number: string, label: string, filled: bool}>
     */
    #[Computed]
    public function pdsMissing(): array
    {
        return array_values(array_filter(
            $this->pdsSections,
            fn (array $section): bool => ! $section['filled'],
        ));
    }
}; ?>

<div class="space-y-6">
    <flux:card class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex min-w-0 items-start gap-4">
            {{-- The picture is the control. A separate "upload" button
                 beside it would say the same thing twice. --}}
            <button type="button" wire:click="choosePhoto" class="group relative shrink-0 cursor-pointer rounded-full"
                aria-label="{{ $this->employee->photo_path === null ? __('Add a photograph') : __('Change your photograph') }}">
                <flux:avatar size="xl" :src="$this->employee->photoUrl()" :name="$this->employee->full_name" />

                <span class="absolute inset-0 flex items-center justify-center rounded-full bg-zinc-900/60 opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">
                    <flux:icon.camera variant="mini" class="text-white" />
                </span>
            </button>

            <div class="min-w-0 space-y-1">
                <flux:heading size="xl">{{ $this->employee->listing_name }}</flux:heading>

                <flux:text>
                    {{ $this->employee->position?->title ?? __('No position on record') }}
                    @if ($this->employee->section)
                        — {{ $this->employee->section->name }}
                    @endif
                </flux:text>

                {{-- The 201 file facts, kept quiet. They are here so the
                     employee can check them, not so they compete with the
                     cards below that actually ask something of them. --}}
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                    <span>{{ __('No.') }} {{ $this->employee->employee_number }}</span>

                    <span aria-hidden="true">·</span>

                    <span>{{ $this->employee->division?->name ?? __('No division') }}</span>

                    <span aria-hidden="true">·</span>

                    <span>{{ $this->employee->employment_status->label() }}</span>

                    @if ($this->employee->date_hired)
                        <span aria-hidden="true">·</span>

                        <span>{{ __('Hired') }} {{ $this->employee->date_hired->format('d M Y') }}</span>
                    @endif
                </div>

                <flux:text size="sm">
                    {{ __('Ask HR to correct anything that is wrong here.') }}
                </flux:text>
            </div>
        </div>

        {{-- The year's units sit with the person rather than alone at the
             foot of the page, and give the picture something to balance. --}}
        <div class="flex items-center gap-6 lg:flex-col lg:items-end lg:gap-3">
            <div class="lg:text-right">
                <flux:text size="sm">{{ __('CPD units in :year', ['year' => now()->year]) }}</flux:text>

                <flux:heading size="xl" class="tabular-nums">
                    {{ rtrim(rtrim(number_format($this->cpdUnits, 1), '0'), '.') }}
                </flux:heading>
            </div>

            <flux:button size="sm" variant="ghost" icon="cog-6-tooth" :href="route('profile.edit')" wire:navigate>
                {{ __('Account settings') }}
            </flux:button>
        </div>
    </flux:card>

    <div class="grid gap-4 lg:grid-cols-2">
        <flux:card class="space-y-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <flux:heading size="lg">{{ __('My PDS') }}</flux:heading>

                <flux:text size="sm">
                    {{ __(':filled of :total sections started', [
                        'filled' => count($this->pdsSections) - count($this->pdsMissing),
                        'total' => count($this->pdsSections),
                    ]) }}
                </flux:text>
            </div>

            <div class="flex items-center gap-3">
                <flux:progress :value="$this->pdsPercentage" class="flex-1" />
                <span class="text-sm tabular-nums">{{ $this->pdsPercentage }}%</span>
            </div>

            @if ($this->pdsMissing === [])
                <flux:callout icon="check-circle" variant="success">
                    {{ __('Every section has something in it. Check it over before you print.') }}
                </flux:callout>
            @else
                <div class="space-y-2">
                    <flux:text size="sm">{{ __('Still empty:') }}</flux:text>

                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->pdsMissing as $section)
                            <flux:badge color="zinc">{{ $section['number'] }}. {{ $section['label'] }}</flux:badge>
                        @endforeach
                    </div>
                </div>
            @endif

            <flux:text size="sm">
                {{ __('Section VI fills itself from your approved training — you do not type it in.') }}
            </flux:text>

            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" variant="primary" :href="route('my-pds')" wire:navigate>
                    {{ __('Fill it in') }}
                </flux:button>

                <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('my-pds.download')">
                    {{ __('Download') }}
                </flux:button>
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('Civil service eligibility') }}</flux:heading>

            @if ($this->employee->eligibilities->isEmpty())
                <flux:callout icon="information-circle">
                    {{ __('Nothing on record yet. Add it under My PDS, Section IV.') }}
                </flux:callout>
            @else
                {{-- One card holding a divided list, rather than a card each. --}}
                <div class="divide-y divide-zinc-200 dark:divide-white/10">
                    @foreach ($this->employee->eligibilities as $eligibility)
                        <div class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="min-w-0">
                                <flux:heading class="break-words">{{ $eligibility->name() }}</flux:heading>

                                <flux:text size="sm">
                                    {{ $eligibility->rating
                                        ? __('Rating :rating', ['rating' => $eligibility->rating])
                                        : __('No rating on record') }}
                                    @if ($eligibility->date_of_examination)
                                        — {{ $eligibility->date_of_examination->format('d M Y') }}
                                    @endif
                                </flux:text>
                            </div>

                            <x-eligibility-expiry :date="$eligibility->date_of_validity" />
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>
    </div>

    <flux:modal name="profile-photo" class="md:w-lg">
        <form wire:submit="savePhoto" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Your photograph') }}</flux:heading>
                <flux:text size="sm">{{ __('JPG, PNG or WEBP, at least 200 by 200 and under 2 MB.') }}</flux:text>
            </div>

            <div class="flex items-center gap-4">
                {{-- What is on show is what will be saved: the chosen file
                     if there is one, otherwise what is already there. --}}
                <flux:avatar size="xl" :src="$this->previewUrl ?? $this->employee->photoUrl()"
                    :name="$this->employee->full_name" />

                <div class="min-w-0 flex-1">
                    <flux:input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp"
                        :label="__('Choose a picture')" />

                    <div wire:loading wire:target="photo">
                        <flux:text size="sm">{{ __('Reading the file…') }}</flux:text>
                    </div>
                </div>
            </div>

            <div class="flex gap-2">
                @if ($this->employee->photo_path !== null)
                    <flux:button size="sm" variant="ghost" wire:click="removePhoto" type="button">
                        {{ __('Remove photograph') }}
                    </flux:button>
                @endif

                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="photo,savePhoto">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
