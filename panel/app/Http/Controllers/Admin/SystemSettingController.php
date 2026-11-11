<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SystemSettingType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreSystemSettingRequest;
use App\Http\Requests\Settings\UpdateSystemSettingRequest;
use App\Http\Requests\Settings\UpdateSystemSettingsRequest;
use App\Http\Resources\System\Setting\SystemSettingResource;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class SystemSettingController extends Controller
{
    /**
     * Display a listing of the settings.
     */
    public function index(Request $request): Response
    {
        $group = $request->query('group', 'general');

        $settings = SystemSetting::visible()
            ->when($group, fn ($query) => $query->whereGroup($group))
            ->sortOrder()
            ->get();

        return Inertia::render('admin/system-settings/Index', [
            'settings' => SystemSettingResource::collection($settings)->resolve(),
            'groups' => SystemSetting::groups()->all(),
            'currentGroup' => $group,
            'fieldTypes' => SystemSettingType::options(),
        ]);
    }

    /**
     * Store a newly created setting in storage.
     */
    public function store(StoreSystemSettingRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Set sort_order to the next available order if not provided
        if (! isset($data['sort_order'])) {
            $group = $data['group'] ?? 'general';
            $data['sort_order'] = SystemSetting::query()->where('group', $group)->max('sort_order') + 1;
        }

        // Decode JSON rules string to array for model cast
        if (isset($data['rules']) && is_string($data['rules'])) {
            $data['rules'] = json_decode($data['rules'], true);
        }

        SystemSetting::query()->create($data);

        return to_route('admin.system-settings.index', ['group' => $data['group'] ?? 'general'])
            ->with('success', 'Setting created successfully.');
    }

    /**
     * Update the specified setting in storage.
     */
    public function update(UpdateSystemSettingRequest $request, SystemSetting $systemSetting): RedirectResponse
    {
        $data = $request->validated();

        // Handle file upload for file and image types
        if (request()->hasFile('file')) {
            $file = request()->file('file');

            // Delete old file if exists
            if ($systemSetting->value && Storage::exists($systemSetting->value)) {
                Storage::delete($systemSetting->value);
            }

            // Store new file using default disk
            $path = $file->store('system-settings');
            $data['value'] = $path;
        }

        // Decode JSON rules string to array for model cast
        if (isset($data['rules']) && is_string($data['rules'])) {
            $data['rules'] = json_decode($data['rules'], true);
        }

        $systemSetting->update($data);

        return to_route('admin.system-settings.index', ['group' => $systemSetting->group])
            ->with('success', 'Setting updated successfully.');
    }

    /**
     * Update multiple settings in storage (bulk update).
     */
    public function bulkUpdate(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        $settings = $request->validated('settings');

        foreach ($settings as $settingData) {
            $setting = SystemSetting::query()->find($settingData['id']);

            if ($setting) {
                // Remove ID from update data
                unset($settingData['id']);

                $setting->update($settingData);
            }
        }

        return back()
            ->with('success', 'Settings updated successfully.');
    }

    /**
     * Remove the specified setting from storage.
     */
    public function destroy(SystemSetting $systemSetting): RedirectResponse
    {
        $group = $systemSetting->group;

        $systemSetting->delete();

        return to_route('admin.system-settings.index', ['group' => $group])
            ->with('success', 'Setting deleted successfully.');
    }

    /**
     * Move the setting up in the sort order.
     */
    public function moveUp(SystemSetting $systemSetting): RedirectResponse
    {
        $systemSetting->moveUp();

        return back()
            ->with('success', 'Setting moved up successfully.');
    }

    /**
     * Move the setting down in the sort order.
     */
    public function moveDown(SystemSetting $systemSetting): RedirectResponse
    {
        $systemSetting->moveDown();

        return back()
            ->with('success', 'Setting moved down successfully.');
    }
}
