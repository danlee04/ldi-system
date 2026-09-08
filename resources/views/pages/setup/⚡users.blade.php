<?php

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('User accounts')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterRole = '';

    public ?int $editingId = null;

    public ?int $employeeId = null;

    public string $name = '';

    public string $email = '';

    public string $role = '';

    public string $password = '';

    public bool $isActive = true;

    public function mount(): void
    {
        abort_unless(auth()->user()->role === UserRole::Admin, 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterRole(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';

                $query->where(fn (Builder $match) => $match->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($this->filterRole !== '', fn (Builder $query) => $query->where('role', $this->filterRole))
            ->with('employee.section')
            ->orderBy('name')
            ->paginate(20);
    }

    /**
     * Employees who cannot sign in yet. The one being edited stays in the
     * list so the form can show who the account belongs to.
     *
     * @return Collection<int, Employee>
     */
    #[Computed]
    public function employeesWithoutAccount(): Collection
    {
        return Employee::query()
            ->active()
            ->where(function (Builder $query): void {
                $query->whereNull('user_id');

                if ($this->editingId !== null) {
                    $query->orWhere('user_id', $this->editingId);
                }
            })
            ->orderBy('last_name')
            ->get();
    }

    public function create(): void
    {
        $this->authorizeAdmin();
        $this->resetForm();

        Flux::modal('user-form')->show();
    }

    public function edit(int $userId): void
    {
        $this->authorizeAdmin();

        $user = User::findOrFail($userId);

        $this->resetValidation();

        $this->editingId = $user->getKey();
        $this->employeeId = $user->employee?->getKey();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->password = '';
        $this->isActive = $user->is_active;

        Flux::modal('user-form')->show();
    }

    public function save(): void
    {
        $this->authorizeAdmin();

        $validated = $this->validate([
            'employeeId' => ['nullable', 'exists:employees,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => [$this->editingId === null ? 'required' : 'nullable', 'string', Password::defaults()],
            'isActive' => ['boolean'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_active' => $validated['isActive'],
        ];

        if ($validated['password'] !== '') {
            $attributes['password'] = $validated['password'];
        }

        if ($this->editingId === null) {
            $attributes['email_verified_at'] = now();
        }

        $user = User::updateOrCreate(['id' => $this->editingId], $attributes);

        $this->linkEmployee($user);

        $this->resetForm();

        unset($this->users, $this->employeesWithoutAccount);

        Flux::modal('user-form')->close();

        Flux::toast(variant: 'success', text: __('Account saved.'));
    }

    /**
     * An employee holds at most one account, so moving an account to a
     * different person must release the previous one.
     */
    private function linkEmployee(User $user): void
    {
        Employee::query()->where('user_id', $user->getKey())
            ->when($this->employeeId !== null, fn (Builder $query) => $query->whereKeyNot($this->employeeId))
            ->update(['user_id' => null]);

        if ($this->employeeId !== null) {
            Employee::query()->whereKey($this->employeeId)->update(['user_id' => $user->getKey()]);
        }
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()->role === UserRole::Admin, 403);
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'employeeId', 'name', 'email', 'role', 'password', 'isActive');
        $this->resetValidation();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('User accounts') }}</flux:heading>

        <flux:button variant="primary" wire:click="create">{{ __('Add account') }}</flux:button>
    </div>

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <flux:input size="sm" class="lg:flex-1" wire:model.live.debounce.300ms="search"
            :placeholder="__('Search name or email')" />

        <flux:select size="sm" class="lg:w-52" wire:model.live="filterRole">
            <flux:select.option value="">{{ __('All roles') }}</flux:select.option>
            @foreach (UserRole::cases() as $case)
                <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->users">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Role') }}</flux:table.column>
            <flux:table.column>{{ __('Employee') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell>
                        <div class="w-44 truncate" title="{{ $user->name }}">{{ $user->name }}</div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="w-56 truncate" title="{{ $user->email }}">{{ $user->email }}</div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->role->label() }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="w-40 truncate" title="{{ $user->employee?->section?->name }}">
                            {{ $user->employee === null ? '—' : $user->employee->employee_number }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($user->is_active)
                            <flux:badge color="green">{{ __('Active') }}</flux:badge>
                        @else
                            <flux:badge color="red">{{ __('Deactivated') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="sm" variant="ghost" wire:click="edit({{ $user->id }})">
                            {{ __('Edit') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('No accounts found.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="user-form" class="md:w-5xl">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId === null ? __('Add account') : __('Edit account') }}
            </flux:heading>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:select class="md:col-span-2" wire:model="employeeId" :label="__('Employee')"
                    :description="__('An account with no employee can sign in but cannot submit or approve.')">
                    <flux:select.option value="">{{ __('Not linked') }}</flux:select.option>
                    @foreach ($this->employeesWithoutAccount as $employee)
                        <flux:select.option :value="$employee->id">
                            {{ $employee->full_name }} — {{ $employee->employee_number }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="name" :label="__('Name')" required />
                <flux:input wire:model="email" :label="__('Email')" type="email" required />

                <flux:select wire:model="role" :label="__('Role')" required>
                    <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                    @foreach (UserRole::cases() as $case)
                        <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:field variant="inline" class="self-end">
                    <flux:switch wire:model="isActive" />
                    <flux:label>{{ __('Can sign in') }}</flux:label>
                </flux:field>

                <flux:input class="md:col-span-2" wire:model="password" :label="__('Password')" type="password"
                    :description="$editingId === null
                        ? __('Give this to the account holder.')
                        : __('Leave empty to keep the current password.')"
                    :required="$editingId === null" />
            </div>

            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary">
                    {{ $editingId === null ? __('Add') : __('Save changes') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
