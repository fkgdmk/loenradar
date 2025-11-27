<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Opdater payslip med specifik URL til Data Analyst
        $dataAnalyst = DB::table('job_titles')
            ->where(function($query) {
                $query->where('name_en', 'Data Analyst')
                      ->orWhere('name', 'Data Analyst');
            })
            ->first();

        if ($dataAnalyst) {
            DB::table('payslips')
                ->where('url', 'https://reddit.com/r/dkloenseddel/comments/1nihr66/power_bi_specialist_controller_wk_aarsleff/')
                ->update(['job_title_id' => $dataAnalyst->id]);
        }

        // 2. Opdater payslips fra Controller til Financial Controller
        $controller = DB::table('job_titles')
            ->where(function($query) {
                $query->where('name_en', 'Controller')
                      ->orWhere('name', 'Controller');
            })
            ->first();

        $financialController = DB::table('job_titles')
            ->where(function($query) {
                $query->where('name_en', 'Financial Controller')
                      ->orWhere('name', 'Financial Controller');
            })
            ->first();

        if ($controller && $financialController) {
            // Opdater payslips
            DB::table('payslips')
                ->where('job_title_id', $controller->id)
                ->update(['job_title_id' => $financialController->id]);

            // Opdater job_postings
            DB::table('job_postings')
                ->where('job_title_id', $controller->id)
                ->update(['job_title_id' => $financialController->id]);
        }

        // 3. Opdater payslips fra Payroll Accountant til Accountant, og slet Payroll Accountant
        $payrollAccountant = DB::table('job_titles')
            ->where(function($query) {
                $query->where('name_en', 'Payroll Accountant')
                      ->orWhere('name', 'Payroll Accountant');
            })
            ->first();

        $accountant = DB::table('job_titles')
            ->where(function($query) {
                $query->where('name_en', 'Accountant')
                      ->orWhere('name', 'Accountant');
            })
            ->first();

        if ($payrollAccountant && $accountant) {
            // Opdater payslips
            DB::table('payslips')
                ->where('job_title_id', $payrollAccountant->id)
                ->update(['job_title_id' => $accountant->id]);

            // Opdater job_postings
            DB::table('job_postings')
                ->where('job_title_id', $payrollAccountant->id)
                ->update(['job_title_id' => $accountant->id]);

            // Slet Payroll Accountant
            DB::table('job_titles')
                ->where('id', $payrollAccountant->id)
                ->delete();
        }

        // 4. Opdater job_title Digital Marketing Specialist til Marketing Specialist
        $digitalMarketingSpecialist = DB::table('job_titles')
            ->where(function($query) {
                $query->where('name_en', 'Digital Marketing Specialist')
                      ->orWhere('name', 'Digital Marketing Specialist');
            })
            ->first();

        $marketingSpecialist = DB::table('job_titles')
            ->where(function($query) {
                $query->where('name_en', 'Marketing Specialist')
                      ->orWhere('name', 'Marketing Specialist');
            })
            ->first();

        if ($digitalMarketingSpecialist && $marketingSpecialist) {
            // Flyt alle relationer fra Digital Marketing Specialist til Marketing Specialist
            DB::table('payslips')
                ->where('job_title_id', $digitalMarketingSpecialist->id)
                ->update(['job_title_id' => $marketingSpecialist->id]);

            DB::table('job_postings')
                ->where('job_title_id', $digitalMarketingSpecialist->id)
                ->update(['job_title_id' => $marketingSpecialist->id]);

            // Slet Digital Marketing Specialist
            DB::table('job_titles')
                ->where('id', $digitalMarketingSpecialist->id)
                ->delete();
        } elseif ($digitalMarketingSpecialist && !$marketingSpecialist) {
            // Hvis Marketing Specialist ikke findes, opdater bare navnet
            DB::table('job_titles')
                ->where('id', $digitalMarketingSpecialist->id)
                ->update([
                    'name' => 'Marketing Specialist',
                    'name_en' => 'Marketing Specialist',
                ]);
        }

        // 5. Opdater payslips fra Manager til Marketing Manager, og slet Manager
        $manager = DB::table('job_titles')
            ->where(function($query) {
                $query->where('name_en', 'Manager')
                      ->orWhere('name', 'Manager');
            })
            ->first();

        $marketingManager = DB::table('job_titles')
            ->where(function($query) {
                $query->where('name_en', 'Marketing Manager')
                      ->orWhere('name', 'Marketing Manager');
            })
            ->first();

        if ($manager && $marketingManager) {
            // Opdater payslips
            DB::table('payslips')
                ->where('job_title_id', $manager->id)
                ->update(['job_title_id' => $marketingManager->id]);

            // Opdater job_postings
            DB::table('job_postings')
                ->where('job_title_id', $manager->id)
                ->update(['job_title_id' => $marketingManager->id]);

            // Slet Manager
            DB::table('job_titles')
                ->where('id', $manager->id)
                ->delete();
        }

        // 6. Opdater payslips fra Afdelingsleder til Team Lead, og slet Afdelingsleder
        $afdelingsleder = DB::table('job_titles')
            ->where(function($query) {
                $query->where('name_en', 'Afdelingsleder')
                      ->orWhere('name', 'Afdelingsleder');
            })
            ->first();

        $teamLead = DB::table('job_titles')
            ->where(function($query) {
                $query->where('name_en', 'Team Lead')
                      ->orWhere('name', 'Team Lead');
            })
            ->first();

        if ($afdelingsleder && $teamLead) {
            // Opdater payslips
            DB::table('payslips')
                ->where('job_title_id', $afdelingsleder->id)
                ->update(['job_title_id' => $teamLead->id]);

            // Opdater job_postings
            DB::table('job_postings')
                ->where('job_title_id', $afdelingsleder->id)
                ->update(['job_title_id' => $teamLead->id]);

            // Slet Afdelingsleder
            DB::table('job_titles')
                ->where('id', $afdelingsleder->id)
                ->delete();
        }

        // 7. Opdater job_title Teamleder / Team Lead til Afdelingsleder / Team Lead
        $teamlederTeamLead = DB::table('job_titles')
            ->where(function($query) {
                $query->where('name_en', 'Teamleder / Team Lead')
                      ->orWhere('name', 'Teamleder / Team Lead');
            })
            ->first();

        if ($teamlederTeamLead) {
            DB::table('job_titles')
                ->where('id', $teamlederTeamLead->id)
                ->update([
                    'name' => 'Afdelingsleder / Team Lead',
                    'name_en' => 'Afdelingsleder / Team Lead',
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Bemærk: Rollback er kompleks da vi sletter job titles
        // Vi kan ikke altid genoprette præcist, men vi prøver at gøre det bedste

        // 5. Rollback Manager
        $marketingManager = DB::table('job_titles')
            ->where(function($query) {
                $query->where('name_en', 'Marketing Manager')
                      ->orWhere('name', 'Marketing Manager');
            })
            ->first();

        if ($marketingManager) {
            // Opret Manager hvis den ikke findes
            $manager = DB::table('job_titles')
                ->where(function($query) {
                    $query->where('name_en', 'Manager')
                          ->orWhere('name', 'Manager');
                })
                ->first();

            if (!$manager) {
                $managerId = DB::table('job_titles')->insertGetId([
                    'name' => 'Manager',
                    'name_en' => 'Manager',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $managerId = $manager->id;
            }

            // Flyt payslips og job_postings tilbage (kun hvis de ikke allerede er opdateret)
            // Dette er ikke perfekt, da vi ikke ved hvilke der oprindeligt var Manager
        }

        // 4. Rollback Digital Marketing Specialist
        $marketingSpecialist = DB::table('job_titles')
            ->where(function($query) {
                $query->where('name_en', 'Marketing Specialist')
                      ->orWhere('name', 'Marketing Specialist');
            })
            ->first();

        if ($marketingSpecialist) {
            // Opret Digital Marketing Specialist hvis den ikke findes
            $digitalMarketingSpecialist = DB::table('job_titles')
                ->where(function($query) {
                    $query->where('name_en', 'Digital Marketing Specialist')
                          ->orWhere('name', 'Digital Marketing Specialist');
                })
                ->first();

            if (!$digitalMarketingSpecialist) {
                DB::table('job_titles')->insert([
                    'name' => 'Digital Marketing Specialist',
                    'name_en' => 'Digital Marketing Specialist',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 3. Rollback Payroll Accountant
        $accountant = DB::table('job_titles')
            ->where(function($query) {
                $query->where('name_en', 'Accountant')
                      ->orWhere('name', 'Accountant');
            })
            ->first();

        if ($accountant) {
            // Opret Payroll Accountant hvis den ikke findes
            $payrollAccountant = DB::table('job_titles')
                ->where(function($query) {
                    $query->where('name_en', 'Payroll Accountant')
                          ->orWhere('name', 'Payroll Accountant');
                })
                ->first();

            if (!$payrollAccountant) {
                DB::table('job_titles')->insert([
                    'name' => 'Payroll Accountant',
                    'name_en' => 'Payroll Accountant',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2. Rollback Controller
        $financialController = DB::table('job_titles')
            ->where(function($query) {
                $query->where('name_en', 'Financial Controller')
                      ->orWhere('name', 'Financial Controller');
            })
            ->first();

        if ($financialController) {
            // Opret Controller hvis den ikke findes
            $controller = DB::table('job_titles')
                ->where(function($query) {
                    $query->where('name_en', 'Controller')
                          ->orWhere('name', 'Controller');
                })
                ->first();

            if (!$controller) {
                DB::table('job_titles')->insert([
                    'name' => 'Controller',
                    'name_en' => 'Controller',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 7. Rollback Teamleder / Team Lead
        $afdelingslederTeamLead = DB::table('job_titles')
            ->where(function($query) {
                $query->where('name_en', 'Afdelingsleder / Team Lead')
                      ->orWhere('name', 'Afdelingsleder / Team Lead');
            })
            ->first();

        if ($afdelingslederTeamLead) {
            DB::table('job_titles')
                ->where('id', $afdelingslederTeamLead->id)
                ->update([
                    'name' => 'Teamleder / Team Lead',
                    'name_en' => 'Teamleder / Team Lead',
                ]);
        }

        // 6. Rollback Afdelingsleder
        $teamLead = DB::table('job_titles')
            ->where(function($query) {
                $query->where('name_en', 'Team Lead')
                      ->orWhere('name', 'Team Lead');
            })
            ->first();

        if ($teamLead) {
            // Opret Afdelingsleder hvis den ikke findes
            $afdelingsleder = DB::table('job_titles')
                ->where(function($query) {
                    $query->where('name_en', 'Afdelingsleder')
                          ->orWhere('name', 'Afdelingsleder');
                })
                ->first();

            if (!$afdelingsleder) {
                DB::table('job_titles')->insert([
                    'name' => 'Afdelingsleder',
                    'name_en' => 'Afdelingsleder',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 1. Rollback specifik payslip URL - nulstil job_title_id til null
        DB::table('payslips')
            ->where('url', 'https://reddit.com/r/dkloenseddel/comments/1nihr66/power_bi_specialist_controller_wk_aarsleff/')
            ->update(['job_title_id' => null]);
    }
};
