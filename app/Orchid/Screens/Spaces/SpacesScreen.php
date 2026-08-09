<?php

namespace App\Orchid\Screens\Spaces;

use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class SpacesScreen extends Screen
{
    /**
     * Matches the permission the menu entry already uses; without it the screen
     * is reachable by URL for anyone who can open the admin panel.
     */
    public function permission(): ?iterable
    {
        return [
            'audit.can_audit',
        ];
    }

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Espacios Publicitarios';
    }

    /**
     * The description is displayed on the user's screen under the heading
     */
    public function description(): ?string
    {
        return 'Explora y gestiona los espacios publicitarios por categoría y ubicación.';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            Layout::view('orchid.spaces-wrapper'),
        ];
    }
}
