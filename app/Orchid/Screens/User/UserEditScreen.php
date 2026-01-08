<?php

declare(strict_types=1);

namespace App\Orchid\Screens\User;

use App\Orchid\Layouts\Role\RolePermissionLayout;
use App\Orchid\Layouts\User\UserEditLayout;
use App\Orchid\Layouts\User\UserPasswordLayout;
use App\Orchid\Layouts\User\UserRoleLayout;
use App\Models\User;
use App\Models\UserNotificationSubscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Orchid\Access\Impersonation;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Matrix;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class UserEditScreen extends Screen
{
    /**
     * @var User
     */
    public $user;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(User $user): iterable
    {
        $user->load(['roles', 'notificationSubscriptions']);

        return [
            'user' => $user,
            'permission' => $user->statusOfPermissions(),
            'subscriptions' => $user->notificationSubscriptions->toArray(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return $this->user->exists ? 'Edit User' : 'Create User';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'User profile, privileges, and notification settings.';
    }

    public function permission(): ?iterable
    {
        return [
            'platform.systems.users',
        ];
    }

    /**
     * The screen's action buttons.
     *
     * @return Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make(__('Impersonate user'))
                ->icon('bg.box-arrow-in-right')
                ->confirm(__('You can revert to your original state by logging out.'))
                ->method('loginAs')
                ->canSee($this->user->exists && $this->user->id !== \request()->user()->id),

            Button::make(__('Remove'))
                ->icon('bs.trash3')
                ->confirm(__('Once the account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.'))
                ->method('remove')
                ->canSee($this->user->exists),

            Button::make(__('Save'))
                ->icon('bs.check-circle')
                ->method('save'),
        ];
    }

    /**
     * @return \Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            Layout::tabs([
                __('Profile Information') => UserEditLayout::class,
                __('Password')            => UserPasswordLayout::class,
                __('Roles')               => UserRoleLayout::class,
                __('Notifications')       => Layout::rows([
                    Matrix::make('subscriptions')
                        ->title('Notification Subscriptions')
                        ->columns([
                            'Event Type' => 'event_type',
                            'Filter Key' => 'filter_key',
                            'Filter Value' => 'filter_value',
                            'Channel' => 'channel',
                        ])
                        ->fields([
                            'event_type' => Select::make()->options([
                                'product_issue' => 'Product Issue (Report)',
                                'system_alert' => 'System Alert',
                            ]),
                            'filter_key' => Select::make()->options([
                                'product_type' => 'Product Type (e.g. VALLAS)',
                                'city' => 'City',
                                'all' => 'All Events',
                            ]),
                            'filter_value' => Input::make()->type('text'),
                            'channel' => Select::make()->options([
                                'email' => 'Email',
                                'sms' => 'SMS',
                            ]),
                        ])
                        ->help('Define which events this user should receive notifications for.'),
                ]),
                __('Permissions')         => RolePermissionLayout::class,
            ]),
        ];
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save(User $user, Request $request)
    {
        $request->validate([
            'user.email' => [
                'required',
                'email',
                Rule::unique(User::class, 'email')->ignore($user),
            ],
            'user.username' => [
                'required',
                'string',
                Rule::unique(User::class, 'username')->ignore($user),
            ]
        ]);

        $permissions = collect($request->get('permissions'))
            ->map(fn($value, $key) => [base64_decode($key) => $value])
            ->collapse()
            ->toArray();

        $user->when($request->filled('user.password'), function (Builder $builder) use ($request) {
            $builder->getModel()->password = Hash::make($request->input('user.password'));
        });

        // Save User Core Data
        $userData = $request->collect('user')->except(['password', 'permissions', 'roles'])->toArray();
        $user->fill($userData)
            ->forceFill(['permissions' => $permissions])
            ->save();

        // Sync Roles
        $user->replaceRoles($request->input('user.roles'));

        // Save Notification Subscriptions
        $subscriptions = $request->input('subscriptions', []);
        $user->notificationSubscriptions()->delete(); // Clear old to easy sync

        foreach ($subscriptions as $sub) {
            // Matrix might return partial rows if empty? usually not if validated but simple foreach is safe
            if (!empty($sub['event_type'])) {
                $user->notificationSubscriptions()->create($sub);
            }
        }

        Toast::info(__('User was saved.'));

        return redirect()->route('platform.systems.users');
    }

    /**
     * @throws \Exception
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function remove(User $user)
    {
        $user->delete();

        Toast::info(__('User was removed'));

        return redirect()->route('platform.systems.users');
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function loginAs(User $user)
    {
        Impersonation::loginAs($user);

        Toast::info(__('You are now impersonating this user'));

        return redirect()->route(config('platform.index'));
    }
}
