<?php

namespace Tests\Feature;

use App\Http\Controllers\Tenant\CompanyController;
use App\Models\Tenant\Company;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Eliminación lógica (deleted_at) y física (confirmando el RUC) de contribuyentes,
 * y restauración al volver a registrar un RUC eliminado lógicamente.
 */
class CompanyDestroyTest extends TestCase
{
    private const RUC = '0791840363001';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();

        Route::middleware('web')->group(function (): void {
            Route::delete('/_test/companies/{company}', [CompanyController::class, 'destroy']);
            Route::post('/_test/companies', [CompanyController::class, 'store']);
        });
    }

    protected function tearDown(): void
    {
        $this->dropSchema();

        parent::tearDown();
    }

    private function dropSchema(): void
    {
        foreach (['shops', 'companies', 'contributor_types'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function createSchema(): void
    {
        $this->dropSchema();

        Schema::create('contributor_types', function (Blueprint $table) {
            $table->id();
            $table->string('description');
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('ruc', 13)->unique();
            $table->string('name', 300);
            $table->string('matrix_address', 300);
            $table->integer('special_contribution')->nullable();
            $table->boolean('accounting')->default(false);
            $table->integer('retention_agent')->nullable();
            $table->boolean('phantom_taxpayer')->default(false);
            $table->boolean('no_transactions')->default(false);
            $table->foreignId('contributor_type_id')->constrained('contributor_types');
            $table->string('phone', 20)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('type_declaration')->nullable();
            $table->string('pass_sri', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('serie', 17);
        });

        DB::table('contributor_types')->insert(['id' => 1, 'description' => 'GENERAL']);
    }

    private function createCompany(): Company
    {
        $company = Company::create([
            'ruc' => self::RUC,
            'name' => 'KLANHG CONSTRUCTORA',
            'matrix_address' => 'MACHALA',
            'contributor_type_id' => 1,
            'type_declaration' => 'mensual',
        ]);

        DB::table('shops')->insert(['company_id' => $company->id, 'serie' => '001-001-000000133']);

        return $company;
    }

    public function test_logical_delete_sets_deleted_at_and_keeps_data(): void
    {
        $company = $this->createCompany();

        $this->delete("/_test/companies/{$company->id}", ['mode' => 'logical'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('companies', ['id' => $company->id]);
        $this->assertDatabaseHas('shops', ['company_id' => $company->id]);
        $this->assertNull(Company::find($company->id));
    }

    public function test_physical_delete_requires_matching_ruc(): void
    {
        $company = $this->createCompany();

        $this->delete("/_test/companies/{$company->id}", ['mode' => 'physical'])
            ->assertSessionHasErrors('confirmation_ruc');

        $this->delete("/_test/companies/{$company->id}", ['mode' => 'physical', 'confirmation_ruc' => '0791840363002'])
            ->assertSessionHasErrors('confirmation_ruc');

        $this->assertNotSoftDeleted('companies', ['id' => $company->id]);
    }

    public function test_physical_delete_removes_company_and_related_data(): void
    {
        $company = $this->createCompany();

        $this->delete("/_test/companies/{$company->id}", ['mode' => 'physical', 'confirmation_ruc' => self::RUC])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
        $this->assertDatabaseMissing('shops', ['company_id' => $company->id]);
    }

    public function test_invalid_mode_is_rejected(): void
    {
        $company = $this->createCompany();

        $this->delete("/_test/companies/{$company->id}", ['mode' => 'other'])
            ->assertSessionHasErrors('mode');

        $this->assertNotSoftDeleted('companies', ['id' => $company->id]);
    }

    public function test_deleting_current_company_clears_session_scope(): void
    {
        $company = $this->createCompany();

        $this->withSession(['current_company_id' => $company->id])
            ->delete("/_test/companies/{$company->id}", ['mode' => 'logical'])
            ->assertSessionMissing('current_company_id');
    }

    public function test_registering_a_logically_deleted_ruc_restores_it(): void
    {
        $company = $this->createCompany();
        $company->delete();

        $this->post('/_test/companies', [
            'ruc' => self::RUC,
            'name' => 'KLANHG CONSTRUCTORA NUEVO',
            'matrix_address' => 'MACHALA',
            'contributor_type_id' => 1,
            'type_declaration' => 'semestral',
            'pass_sri' => 'secret',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $restored = Company::find($company->id);

        $this->assertNotNull($restored);
        $this->assertNull($restored->deleted_at);
        $this->assertSame('KLANHG CONSTRUCTORA NUEVO', $restored->name);
        $this->assertSame(1, Company::withTrashed()->where('ruc', self::RUC)->count());
        $this->assertDatabaseHas('shops', ['company_id' => $company->id]);
    }

    public function test_registering_an_active_ruc_fails_validation(): void
    {
        $this->createCompany();

        $this->post('/_test/companies', [
            'ruc' => self::RUC,
            'name' => 'DUPLICADO',
            'matrix_address' => 'MACHALA',
            'contributor_type_id' => 1,
            'type_declaration' => 'mensual',
            'pass_sri' => 'secret',
        ])->assertSessionHasErrors('ruc');
    }
}
