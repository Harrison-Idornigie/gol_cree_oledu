<?php
namespace Database\Seeders\Tenant;

use App\Models\Tenants\Language;
use App\Models\Tenants\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Authenticate as the first user for content versioning
        $user = User::first();
        if ($user) {
            Auth::login($user);
        }

        // Create languages
        $languages = [
            [
                'code'        => 'en',
                'name'        => 'English',
                'native_name' => 'English',
                'is_active'   => true,
            ],
            [
                'code'        => 'es',
                'name'        => 'Spanish',
                'native_name' => 'Español',
                'is_active'   => true,
            ],
            [
                'code'        => 'crk',
                'name'        => 'Plains Cree',
                'native_name' => 'nēhiyawēwin',
                'is_active'   => true,
            ],
        ];

        foreach ($languages as $language) {
            Language::updateOrCreate(
                ['code' => $language['code']],
                $language
            );
        }
    }
}
