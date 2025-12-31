<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Orchid\Platform\Models\Role;
use App\Models\User;

class ImportLegacyData extends Command
{
    protected $signature = 'import:legacy';
    protected $description = 'Import data from the legacy "efectimedios" database (elemento, historial_estado, etc)';

    public function handle()
    {
        $this->info('Starting legacy data import...');

        // 1. Configure Legacy Connection
        $legacyConfig = array_merge(
            config('database.connections.mysql'),
            ['database' => 'efectimedios']
        );
        Config::set('database.connections.legacy', $legacyConfig);
        $legacyDb = DB::connection('legacy');

        try {
            $legacyDb->getPdo();
            $this->info('Connected to legacy database successfully.');
        } catch (\Exception $e) {
            $this->error('Could not connect to legacy database: ' . $e->getMessage());
            return 1;
        }

        if (!$this->confirm('This will TRUNCATE existing tables (users, roles, advertising_spaces, audits). Continue?', true)) {
            return 0;
        }

        // 2. Clear new tables
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('users')->truncate();
        DB::table('roles')->truncate();
        DB::table('role_users')->truncate();
        DB::table('advertising_spaces')->truncate();
        DB::table('audits')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 3. Import Roles ('tipousuario')
        $this->info('Importing Roles...');
        $legacyRoles = $legacyDb->table('tipousuario')->get();
        $roleMap = []; // old_id => new_slug

        foreach ($legacyRoles as $lRole) {
            $roleName = trim($lRole->nombreTipoUsu);
            $slug = Str::slug($roleName);

            // Create or find role in Orchid
            $roleId = DB::table('roles')->insertGetId([
                'slug' => $slug,
                'name' => $roleName,
                'permissions' => json_encode([]), // Default empty permissions
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $roleMap[$lRole->idTipoUsu] = $roleId;
        }
        $this->info('Roles imported: ' . count($roleMap));


        // 4. Import Users ('usuarios') - ONLY ACTIVE USERS
        $this->info('Importing Active Users...');
        // Filter by state 'A' (Activo)
        $legacyUsers = $legacyDb->table('usuarios')
            ->where('estado', 'A')
            ->get();

        $bar = $this->output->createProgressBar(count($legacyUsers));

        $importedEmails = [];
        $importedUsernames = [];

        foreach ($legacyUsers as $lUser) {
            // --- Process Username ---
            $rawUsername = trim($lUser->usuario);
            if (empty($rawUsername)) {
                $username = 'user_' . $lUser->idUsuario;
            } else {
                $username = $rawUsername;
            }

            if (in_array($username, $importedUsernames)) {
                $username = $username . '_' . $lUser->idUsuario;
            }
            $importedUsernames[] = $username;

            // --- Process Email ---
            $rawEmail = trim($lUser->mailUsuario);
            if (empty($rawEmail)) {
                $email = strtolower($username) . '_' . $lUser->idUsuario . '@temp.checkmedia';
            } else {
                $email = strtolower($rawEmail);
            }

            if (in_array($email, $importedEmails)) {
                $email = $lUser->idUsuario . '_duplicate_' . $email;
            }
            $importedEmails[] = $email;

            // Insert User
            DB::table('users')->insert([
                'id' => $lUser->idUsuario,
                'name' => trim($lUser->nombre . ' ' . $lUser->apellido),
                'username' => $username,
                'email' => $email,
                'password' => Hash::make('12345678'),
                'must_change_password' => true,
                'is_active' => true, // We are strictly importing active users
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign Role
            if (isset($lUser->tipoUsuario) && isset($roleMap[$lUser->tipoUsuario])) {
                DB::table('role_users')->insert([
                    'user_id' => $lUser->idUsuario,
                    'role_id' => $roleMap[$lUser->tipoUsuario]
                ]);
            }

            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        // 5. Import Elements ('elemento')
        $this->info('Importing Advertising Spaces from "elemento"...');

        if ($legacyDb->getSchemaBuilder()->hasTable('elemento')) {
            $legacyElements = $legacyDb->table('elemento')->get();
            $bar = $this->output->createProgressBar(count($legacyElements));

            foreach ($legacyElements as $lElem) {
                $illumination = $lElem->illuminacionEle ?? $lElem->iluminacionEle ?? 'NO';

                DB::table('advertising_spaces')->insert([
                    'external_code' => $lElem->espacioCod,
                    'provider' => $lElem->proveedorEle ?? null,
                    'type' => $lElem->tipoEle ?? null,
                    'city' => $lElem->ciudadEle ?? 'Unknown',
                    'location_name' => $lElem->locacionEle ?? null,
                    'address' => $lElem->localizacionEle ?? $lElem->ubicacionEle ?? null,
                    'product_type' => $lElem->productoEle ?? null,
                    'has_illumination' => ($illumination == 'SI') ? 1 : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
        } else {
            $this->error('Table "elemento" not found!');
        }

        // 6. Import Audits ('historial_estado')
        $this->info('Importing Audits from "historial_estado"...');

        // Cache Valid User IDs to avoid FK Violations
        $validUserIds = DB::table('users')->pluck('id')->toArray();
        $this->info('Valid User IDs loaded: ' . count($validUserIds));

        if ($legacyDb->getSchemaBuilder()->hasTable('historial_estado')) {
            $query = $legacyDb->table('historial_estado')
                ->join('estado_ele', 'historial_estado.idEstado', '=', 'estado_ele.idEstado')
                ->select('historial_estado.*', 'estado_ele.espacioCod', 'estado_ele.fechaEstado')
                ->orderBy('historial_estado.idEdit', 'desc');

            $bar = $this->output->createProgressBar($query->count());

            // Process in chunks
            $query->chunk(500, function ($rows) use ($bar, $validUserIds) {
                foreach ($rows as $row) {
                    $spaceId = DB::table('advertising_spaces')
                        ->where('external_code', $row->espacioCod)
                        ->value('id');

                    if (!$spaceId)
                        continue;

                    // --- Stricter Date Validation ---
                    $isValidDate = function ($d) {
                        if (empty($d))
                            return false;
                        if ($d === '0000-00-00')
                            return false;
                        if (strpos($d, '-00') !== false)
                            return false;
                        try {
                            $t = \Carbon\Carbon::parse($d);
                            return $t && $t->year > 1900;
                        } catch (\Exception $e) {
                            return false;
                        }
                    };

                    $auditDate = $row->fechaEdit;
                    if (!$isValidDate($auditDate)) {
                        if ($isValidDate($row->fechaEstado)) {
                            $auditDate = $row->fechaEstado;
                        } else {
                            $auditDate = '1970-01-01';
                        }
                    }
                    if (!$isValidDate($auditDate))
                        $auditDate = '1970-01-01';

                    // --- Handle Missing User (Inactive) ---
                    $userId = $row->idUsuario;
                    $observation = $row->estadoNota ?? null;

                    if (!in_array($userId, $validUserIds)) {
                        $observation .= " [Legacy User ID: $userId]";
                        $userId = null; // Valid because column is nullable
                    }

                    $status = 'good';
                    if ($row->anomaliaEstado > 0 || $row->estadoEstado > 1) {
                        $status = 'bad';
                    }

                    DB::table('audits')->insert([
                        'advertising_space_id' => $spaceId,
                        'user_id' => $userId,
                        'year' => date('Y', strtotime($auditDate)),
                        'week' => date('W', strtotime($auditDate)),
                        'audit_date' => $auditDate,
                        'general_status' => $status,
                        'observation' => trim($observation),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $bar->advance();
                }
            });

            $bar->finish();
            $this->newLine();
        } else {
            $this->warn('Table "historial_estado" not found. Skipping audits.');
        }

        $this->info('Data Import Completed!');
        return 0;
    }
}
