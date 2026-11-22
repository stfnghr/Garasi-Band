<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthAndVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function registrationData(array $overrides = []): array
    {
        return [
            'nik' => '3578010101900001',
            'name' => 'Andi User',
            'phone' => '081234567890',
            'email' => 'andi@example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            ...$overrides,
        ];
    }

    #[Test]
    public function ut_61_pendaftar_baru_berstatus_pending_dan_belum_bisa_login(): void
    {
        $this->post('/register', $this->registrationData())->assertRedirect(route('login'));

        $user = User::where('email', 'andi@example.com')->firstOrFail();
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertSame(UserRole::User, $user->role);   // pendaftar selalu role user (penyewa)

        $this->post('/login', ['email' => 'andi@example.com', 'password' => 'rahasia123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    #[Test]
    public function ut_62_nik_harus_16_digit_dan_unik(): void
    {
        $this->post('/register', $this->registrationData(['nik' => '123']))->assertSessionHasErrors('nik');

        User::factory()->create(['nik' => '3578010101900001']);
        $this->post('/register', $this->registrationData(['email' => 'lain@example.com']))->assertSessionHasErrors('nik');
    }

    #[Test]
    public function ut_63_admin_menerima_pendaftar_lalu_user_bisa_login(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->pending()->create(['password' => 'rahasia123']);

        $this->actingAs($admin)->patch(route('admin.users.approve', $user))->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertSame(UserStatus::Approved, $user->status);
        $this->assertSame($admin->id, $user->verified_by);

        auth()->logout();
        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia123'])->assertRedirect(route('user.rentals.index'));
    }

    #[Test]
    public function ut_64_admin_menolak_pendaftar(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->pending()->create();

        $this->actingAs($admin)->patch(route('admin.users.reject', $user));

        $this->assertSame(UserStatus::Rejected, $user->fresh()->status);
    }

    #[Test]
    public function ut_65_hanya_dua_role_dan_akses_dibatasi_per_role(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.garages.index'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('user.rentals.create'))->assertForbidden();

        // Aplikasi hanya memiliki dua role: admin dan user.
        $this->assertSame(['admin', 'user'], array_column(UserRole::cases(), 'value'));
    }
}
