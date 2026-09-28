<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\PatientImage;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PatientImagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
    }

    protected function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    protected function patient(): Patient
    {
        return Patient::create(['name' => 'Jane Nakato', 'phone' => '0772000000', 'dob' => '1990-05-12']);
    }

    public function test_assistant_can_upload_xrays_to_private_storage(): void
    {
        $assistant = $this->userWithRole('assistant');
        $patient = $this->patient();

        $this->actingAs($assistant)->post(route('patients.images.store', $patient), [
            'files' => [UploadedFile::fake()->image('pa-36.jpg'), UploadedFile::fake()->image('pa-37.png')],
            'category' => 'periapical',
            'teeth' => '36, 37',
            'taken_at' => now()->toDateString(),
            'notes' => 'Pre-RCT',
        ])->assertRedirect(route('patients.images.index', $patient));

        $this->assertSame(2, $patient->images()->count());
        $image = $patient->images()->first();
        $this->assertSame('36,37', $image->teeth);
        $this->assertSame($assistant->id, $image->uploaded_by);
        Storage::disk('local')->assertExists($image->path);
        $this->assertStringStartsWith("patients/{$patient->id}/imaging/", $image->path);
    }

    public function test_index_lists_images(): void
    {
        $admin = $this->userWithRole('admin');
        $patient = $this->patient();
        $this->actingAs($admin)->post(route('patients.images.store', $patient), [
            'files' => [UploadedFile::fake()->image('opg.jpg')],
            'category' => 'panoramic',
        ]);

        $this->actingAs($admin)->get(route('patients.images.index', $patient))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Patients/Imaging')
                ->has('images', 1)
                ->where('images.0.category', 'panoramic')
                ->where('can_upload', true));
    }

    public function test_rejects_disallowed_files_and_bad_tooth_numbers(): void
    {
        $admin = $this->userWithRole('admin');
        $patient = $this->patient();

        $this->actingAs($admin)->post(route('patients.images.store', $patient), [
            'files' => [UploadedFile::fake()->create('virus.exe', 10)],
            'category' => 'periapical',
            'teeth' => 'lower left',
        ])->assertSessionHasErrors(['files.0', 'teeth']);

        $this->assertSame(0, PatientImage::count());
    }

    public function test_file_is_only_served_to_staff_who_can_view_the_patient(): void
    {
        $admin = $this->userWithRole('admin');
        $patient = $this->patient();
        $this->actingAs($admin)->post(route('patients.images.store', $patient), [
            'files' => [UploadedFile::fake()->image('bw.jpg')],
            'category' => 'bitewing',
        ]);
        $image = $patient->images()->first();

        $this->actingAs($admin)->get(route('patients.images.file', [$patient, $image]))->assertOk();

        // A dentist with no appointment for this patient cannot view it
        $dentist = $this->userWithRole('dentist');
        $this->actingAs($dentist)->get(route('patients.images.file', [$patient, $image]))->assertForbidden();

        // Once they have an appointment with the patient, they can
        Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'start_time' => now(),
            'end_time' => now()->addHour(),
            'status' => 'scheduled',
            'type' => 'Checkup',
        ]);
        $this->actingAs($dentist)->get(route('patients.images.file', [$patient, $image]))->assertOk();

        // An image can't be fetched through a different patient's URL
        $other = Patient::create(['name' => 'Other Patient', 'phone' => '0701000000', 'dob' => '1985-01-01']);
        $this->actingAs($admin)->get(route('patients.images.file', [$other, $image]))->assertNotFound();
    }

    public function test_only_admin_or_uploader_can_delete_and_delete_is_soft(): void
    {
        $assistant = $this->userWithRole('assistant');
        $otherAssistant = $this->userWithRole('assistant');
        $patient = $this->patient();
        $this->actingAs($assistant)->post(route('patients.images.store', $patient), [
            'files' => [UploadedFile::fake()->image('photo.jpg')],
            'category' => 'intraoral_photo',
        ]);
        $image = $patient->images()->first();

        $this->actingAs($otherAssistant)->delete(route('patients.images.destroy', [$patient, $image]))->assertForbidden();

        $this->actingAs($assistant)->delete(route('patients.images.destroy', [$patient, $image]))
            ->assertRedirect(route('patients.images.index', $patient));

        $this->assertSoftDeleted($image);
        Storage::disk('local')->assertExists($image->path);
    }
}
