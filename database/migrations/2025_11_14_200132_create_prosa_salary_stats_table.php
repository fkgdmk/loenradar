<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ProsaJobCategory;
use App\Models\ProsaArea;
use App\Models\ProsaSalaryStat;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prosa_salary_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prosa_category_id')->constrained('prosa_job_categories')->onDelete('cascade');
            $table->foreignId('prosa_area_id')->constrained('prosa_areas')->onDelete('cascade');
            $table->integer('experience_from');
            $table->integer('experience_to');
            $table->decimal('lower_quartile_salary', 10, 2)->nullable();
            $table->decimal('median_salary', 10, 2)->nullable();
            $table->decimal('upper_quartile_salary', 10, 2)->nullable();
            $table->decimal('average_salary', 10, 2)->nullable();
            $table->integer('sample_size')->nullable();
            $table->year('statistic_year');
            $table->timestamps();
            
            $table->index(['experience_from', 'experience_to'], 'idx_experience_range');
            $table->index('statistic_year', 'idx_statistic_year');
        });

        // Get category and area IDs
        $planCategory = ProsaJobCategory::where('category_name', 'Plan')->first();
        $buildCategory = ProsaJobCategory::where('category_name', 'Build')->first();
        $runCategory = ProsaJobCategory::where('category_name', 'Run')->first();
        $enableCategory = ProsaJobCategory::where('category_name', 'Enable')->first();
        $managementCategory = ProsaJobCategory::where('category_name', 'Management')->first();
        $eastArea = ProsaArea::where('area_name', 'Øst for Storebælt')->first();
        $westArea = ProsaArea::where('area_name', 'Vest for Storebælt')->first();

        if ($planCategory && $eastArea) {
            $salaryStats = [
                [
                    'prosa_category_id' => $planCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 0,
                    'experience_to' => 4,
                    'lower_quartile_salary' => null, // "For få besvarelser"
                    'median_salary' => null, // "For få besvarelser"
                    'upper_quartile_salary' => null, // "For få besvarelser"
                    'average_salary' => 38855,
                    'sample_size' => 4,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $planCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 5,
                    'experience_to' => 9,
                    'lower_quartile_salary' => 48954,
                    'median_salary' => 50653,
                    'upper_quartile_salary' => 58000,
                    'average_salary' => 52099,
                    'sample_size' => 26,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $planCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 10,
                    'experience_to' => 14,
                    'lower_quartile_salary' => 58048,
                    'median_salary' => 65749,
                    'upper_quartile_salary' => 76688,
                    'average_salary' => 67630,
                    'sample_size' => 29,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $planCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 15,
                    'experience_to' => 19,
                    'lower_quartile_salary' => 67807,
                    'median_salary' => 74250,
                    'upper_quartile_salary' => 88764,
                    'average_salary' => 77955,
                    'sample_size' => 19,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $planCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 20,
                    'experience_to' => 24,
                    'lower_quartile_salary' => 73138,
                    'median_salary' => 83938,
                    'upper_quartile_salary' => 102221,
                    'average_salary' => 85514,
                    'sample_size' => 16,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $planCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 25,
                    'experience_to' => 29,
                    'lower_quartile_salary' => 67464,
                    'median_salary' => 79744,
                    'upper_quartile_salary' => 82261,
                    'average_salary' => 78175,
                    'sample_size' => 16,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $planCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 30,
                    'experience_to' => 34,
                    'lower_quartile_salary' => 79743,
                    'median_salary' => 82918,
                    'upper_quartile_salary' => 103136,
                    'average_salary' => 87655,
                    'sample_size' => 6,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $planCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 35,
                    'experience_to' => 39,
                    'lower_quartile_salary' => 73125,
                    'median_salary' => 80973,
                    'upper_quartile_salary' => 88176,
                    'average_salary' => 78324,
                    'sample_size' => 6,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $planCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 40,
                    'experience_to' => 99, // "over 39 år"
                    'lower_quartile_salary' => 73450,
                    'median_salary' => 73890,
                    'upper_quartile_salary' => 78694,
                    'average_salary' => 73944,
                    'sample_size' => 5,
                    'statistic_year' => 2024,
                ],
            ];

            foreach ($salaryStats as $stat) {
                ProsaSalaryStat::create($stat);
            }
        }

        // Insert Build category salary stats
        if ($buildCategory && $eastArea) {
            $buildSalaryStats = [
                [
                    'prosa_category_id' => $buildCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 0,
                    'experience_to' => 4,
                    'lower_quartile_salary' => 41933,
                    'median_salary' => 43643,
                    'upper_quartile_salary' => 52725,
                    'average_salary' => 48541,
                    'sample_size' => 19,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $buildCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 5,
                    'experience_to' => 9,
                    'lower_quartile_salary' => 45848,
                    'median_salary' => 52691,
                    'upper_quartile_salary' => 61875,
                    'average_salary' => 54826,
                    'sample_size' => 103,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $buildCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 10,
                    'experience_to' => 14,
                    'lower_quartile_salary' => 54874,
                    'median_salary' => 61875,
                    'upper_quartile_salary' => 69300,
                    'average_salary' => 62540,
                    'sample_size' => 109,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $buildCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 15,
                    'experience_to' => 19,
                    'lower_quartile_salary' => 60750,
                    'median_salary' => 69011,
                    'upper_quartile_salary' => 76561,
                    'average_salary' => 68425,
                    'sample_size' => 56,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $buildCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 20,
                    'experience_to' => 24,
                    'lower_quartile_salary' => 68282,
                    'median_salary' => 75211,
                    'upper_quartile_salary' => 80873,
                    'average_salary' => 74695,
                    'sample_size' => 46,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $buildCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 25,
                    'experience_to' => 29,
                    'lower_quartile_salary' => 65999,
                    'median_salary' => 76664,
                    'upper_quartile_salary' => 87279,
                    'average_salary' => 77342,
                    'sample_size' => 36,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $buildCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 30,
                    'experience_to' => 34,
                    'lower_quartile_salary' => 70445,
                    'median_salary' => 76970,
                    'upper_quartile_salary' => 83260,
                    'average_salary' => 76975,
                    'sample_size' => 29,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $buildCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 35,
                    'experience_to' => 39,
                    'lower_quartile_salary' => 70081,
                    'median_salary' => 74806,
                    'upper_quartile_salary' => 82841,
                    'average_salary' => 77877,
                    'sample_size' => 18,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $buildCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 40,
                    'experience_to' => 99, // "over 39 år"
                    'lower_quartile_salary' => 67119,
                    'median_salary' => 71131,
                    'upper_quartile_salary' => 77702,
                    'average_salary' => 72158,
                    'sample_size' => 21,
                    'statistic_year' => 2024,
                ],
            ];

            foreach ($buildSalaryStats as $stat) {
                ProsaSalaryStat::create($stat);
            }
        }

        // Insert Run category salary stats
        if ($runCategory && $eastArea) {
            $runSalaryStats = [
                [
                    'prosa_category_id' => $runCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 0,
                    'experience_to' => 4,
                    'lower_quartile_salary' => 35775,
                    'median_salary' => 45100,
                    'upper_quartile_salary' => 48949,
                    'average_salary' => 42334,
                    'sample_size' => 17,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $runCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 5,
                    'experience_to' => 9,
                    'lower_quartile_salary' => 47680,
                    'median_salary' => 55368,
                    'upper_quartile_salary' => 64243,
                    'average_salary' => 55187,
                    'sample_size' => 36,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $runCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 10,
                    'experience_to' => 14,
                    'lower_quartile_salary' => 42439,
                    'median_salary' => 49640,
                    'upper_quartile_salary' => 60750,
                    'average_salary' => 52405,
                    'sample_size' => 18,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $runCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 15,
                    'experience_to' => 19,
                    'lower_quartile_salary' => 48574,
                    'median_salary' => 61039,
                    'upper_quartile_salary' => 74659,
                    'average_salary' => 61526,
                    'sample_size' => 13,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $runCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 20,
                    'experience_to' => 24,
                    'lower_quartile_salary' => 54230,
                    'median_salary' => 61679,
                    'upper_quartile_salary' => 73131,
                    'average_salary' => 64495,
                    'sample_size' => 32,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $runCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 25,
                    'experience_to' => 29,
                    'lower_quartile_salary' => 58914,
                    'median_salary' => 65756,
                    'upper_quartile_salary' => 70967,
                    'average_salary' => 65817,
                    'sample_size' => 38,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $runCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 30,
                    'experience_to' => 34,
                    'lower_quartile_salary' => 55000,
                    'median_salary' => 65944,
                    'upper_quartile_salary' => 76778,
                    'average_salary' => 65304,
                    'sample_size' => 19,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $runCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 35,
                    'experience_to' => 39,
                    'lower_quartile_salary' => 68153,
                    'median_salary' => 75432,
                    'upper_quartile_salary' => 80947,
                    'average_salary' => 75963,
                    'sample_size' => 13,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $runCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 40,
                    'experience_to' => 99, // "over 39 år"
                    'lower_quartile_salary' => 66649,
                    'median_salary' => 76014,
                    'upper_quartile_salary' => 83721,
                    'average_salary' => 76464,
                    'sample_size' => 26,
                    'statistic_year' => 2024,
                ],
            ];

            foreach ($runSalaryStats as $stat) {
                ProsaSalaryStat::create($stat);
            }
        }

        // Insert Enable category salary stats
        if ($enableCategory && $eastArea) {
            $enableSalaryStats = [
                [
                    'prosa_category_id' => $enableCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 0,
                    'experience_to' => 4,
                    'lower_quartile_salary' => null, // "For få besvarelser"
                    'median_salary' => null, // "For få besvarelser"
                    'upper_quartile_salary' => null, // "For få besvarelser"
                    'average_salary' => 41376,
                    'sample_size' => 4,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $enableCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 5,
                    'experience_to' => 9,
                    'lower_quartile_salary' => 48300,
                    'median_salary' => 55100,
                    'upper_quartile_salary' => 62591,
                    'average_salary' => 57335,
                    'sample_size' => 19,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $enableCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 10,
                    'experience_to' => 14,
                    'lower_quartile_salary' => 55309,
                    'median_salary' => 61915,
                    'upper_quartile_salary' => 73750,
                    'average_salary' => 63558,
                    'sample_size' => 11,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $enableCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 15,
                    'experience_to' => 19,
                    'lower_quartile_salary' => 61210,
                    'median_salary' => 78056,
                    'upper_quartile_salary' => 92361,
                    'average_salary' => 79361,
                    'sample_size' => 12,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $enableCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 20,
                    'experience_to' => 24,
                    'lower_quartile_salary' => null, // "For få besvarelser"
                    'median_salary' => null, // "For få besvarelser"
                    'upper_quartile_salary' => null, // "For få besvarelser"
                    'average_salary' => null,
                    'sample_size' => 2,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $enableCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 25,
                    'experience_to' => 29,
                    'lower_quartile_salary' => 62222,
                    'median_salary' => 80148,
                    'upper_quartile_salary' => 94500,
                    'average_salary' => 81026,
                    'sample_size' => 10,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $enableCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 30,
                    'experience_to' => 34,
                    'lower_quartile_salary' => 71997,
                    'median_salary' => 82340,
                    'upper_quartile_salary' => 86697,
                    'average_salary' => 77456,
                    'sample_size' => 7,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $enableCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 35,
                    'experience_to' => 39,
                    'lower_quartile_salary' => null, // "For få besvarelser"
                    'median_salary' => null, // "For få besvarelser"
                    'upper_quartile_salary' => null, // "For få besvarelser"
                    'average_salary' => null,
                    'sample_size' => 1,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $enableCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 40,
                    'experience_to' => 99, // "over 39 år"
                    'lower_quartile_salary' => 67119,
                    'median_salary' => 71131,
                    'upper_quartile_salary' => 77702,
                    'average_salary' => 72158,
                    'sample_size' => 21,
                    'statistic_year' => 2024,
                ],
            ];

            foreach ($enableSalaryStats as $stat) {
                ProsaSalaryStat::create($stat);
            }
        }

        // Insert Management category salary stats
        if ($managementCategory && $eastArea) {
            $managementSalaryStats = [
                [
                    'prosa_category_id' => $managementCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 0,
                    'experience_to' => 4,
                    'lower_quartile_salary' => 44287,
                    'median_salary' => 48784,
                    'upper_quartile_salary' => 49445,
                    'average_salary' => 51006,
                    'sample_size' => 5,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $managementCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 5,
                    'experience_to' => 9,
                    'lower_quartile_salary' => 53018,
                    'median_salary' => 58781,
                    'upper_quartile_salary' => 69801,
                    'average_salary' => 59302,
                    'sample_size' => 10,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $managementCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 10,
                    'experience_to' => 14,
                    'lower_quartile_salary' => 53460,
                    'median_salary' => 63977,
                    'upper_quartile_salary' => 75600,
                    'average_salary' => 64950,
                    'sample_size' => 34,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $managementCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 15,
                    'experience_to' => 19,
                    'lower_quartile_salary' => 65831,
                    'median_salary' => 76452,
                    'upper_quartile_salary' => 85909,
                    'average_salary' => 77959,
                    'sample_size' => 26,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $managementCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 20,
                    'experience_to' => 24,
                    'lower_quartile_salary' => 64608,
                    'median_salary' => 73142,
                    'upper_quartile_salary' => 89062,
                    'average_salary' => 73855,
                    'sample_size' => 11,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $managementCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 25,
                    'experience_to' => 29,
                    'lower_quartile_salary' => 64616,
                    'median_salary' => 75116,
                    'upper_quartile_salary' => 83439,
                    'average_salary' => 76367,
                    'sample_size' => 23,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $managementCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 30,
                    'experience_to' => 34,
                    'lower_quartile_salary' => 69562,
                    'median_salary' => 76643,
                    'upper_quartile_salary' => 87395,
                    'average_salary' => 75637,
                    'sample_size' => 12,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $managementCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 35,
                    'experience_to' => 39,
                    'lower_quartile_salary' => null, // "For få besvarelser"
                    'median_salary' => null, // "For få besvarelser"
                    'upper_quartile_salary' => null, // "For få besvarelser"
                    'average_salary' => 95199,
                    'sample_size' => 4,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $managementCategory->id,
                    'prosa_area_id' => $eastArea->id,
                    'experience_from' => 40,
                    'experience_to' => 99, // "over 39 år"
                    'lower_quartile_salary' => 62388,
                    'median_salary' => 79445,
                    'upper_quartile_salary' => 95134,
                    'average_salary' => 78396,
                    'sample_size' => 6,
                    'statistic_year' => 2024,
                ],
            ];

            foreach ($managementSalaryStats as $stat) {
                ProsaSalaryStat::create($stat);
            }
        }

        // Insert Plan category salary stats for Vest for Storebælt
        if ($planCategory && $westArea) {
            $planWestSalaryStats = [
                [
                    'prosa_category_id' => $planCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 0,
                    'experience_to' => 4,
                    'lower_quartile_salary' => null, // "For få besvarelser"
                    'median_salary' => null, // "For få besvarelser"
                    'upper_quartile_salary' => null, // "For få besvarelser"
                    'average_salary' => null, // "For få besvarelser"
                    'sample_size' => 2,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $planCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 5,
                    'experience_to' => 9,
                    'lower_quartile_salary' => 41294,
                    'median_salary' => 44499,
                    'upper_quartile_salary' => 55125,
                    'average_salary' => 47740,
                    'sample_size' => 20,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $planCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 10,
                    'experience_to' => 14,
                    'lower_quartile_salary' => 52159,
                    'median_salary' => 60104,
                    'upper_quartile_salary' => 63818,
                    'average_salary' => 58440,
                    'sample_size' => 20,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $planCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 15,
                    'experience_to' => 19,
                    'lower_quartile_salary' => 59625,
                    'median_salary' => 67543,
                    'upper_quartile_salary' => 75273,
                    'average_salary' => 67880,
                    'sample_size' => 12,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $planCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 20,
                    'experience_to' => 24,
                    'lower_quartile_salary' => 56721,
                    'median_salary' => 69967,
                    'upper_quartile_salary' => 74967,
                    'average_salary' => 69141,
                    'sample_size' => 10,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $planCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 25,
                    'experience_to' => 29,
                    'lower_quartile_salary' => 63432,
                    'median_salary' => 72058,
                    'upper_quartile_salary' => 79120,
                    'average_salary' => 72220,
                    'sample_size' => 6,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $planCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 30,
                    'experience_to' => 34,
                    'lower_quartile_salary' => 65345,
                    'median_salary' => 72142,
                    'upper_quartile_salary' => 84574,
                    'average_salary' => 73945,
                    'sample_size' => 5,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $planCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 35,
                    'experience_to' => 39,
                    'lower_quartile_salary' => 65138,
                    'median_salary' => 67476,
                    'upper_quartile_salary' => 87821,
                    'average_salary' => 74202,
                    'sample_size' => 6,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $planCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 40,
                    'experience_to' => 99, // "over 39 år"
                    'lower_quartile_salary' => 73450,
                    'median_salary' => 73890,
                    'upper_quartile_salary' => 78694,
                    'average_salary' => 73944,
                    'sample_size' => 5,
                    'statistic_year' => 2024,
                ],
            ];

            foreach ($planWestSalaryStats as $stat) {
                ProsaSalaryStat::create($stat);
            }
        }

        // Insert Build category salary stats for Vest for Storebælt
        if ($buildCategory && $westArea) {
            $buildWestSalaryStats = [
                [
                    'prosa_category_id' => $buildCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 0,
                    'experience_to' => 4,
                    'lower_quartile_salary' => 33750,
                    'median_salary' => 38500,
                    'upper_quartile_salary' => 40765,
                    'average_salary' => 38442,
                    'sample_size' => 41,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $buildCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 5,
                    'experience_to' => 9,
                    'lower_quartile_salary' => 42856,
                    'median_salary' => 47199,
                    'upper_quartile_salary' => 52000,
                    'average_salary' => 47750,
                    'sample_size' => 130,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $buildCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 10,
                    'experience_to' => 14,
                    'lower_quartile_salary' => 48436,
                    'median_salary' => 55360,
                    'upper_quartile_salary' => 61580,
                    'average_salary' => 56028,
                    'sample_size' => 92,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $buildCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 15,
                    'experience_to' => 19,
                    'lower_quartile_salary' => 53436,
                    'median_salary' => 60116,
                    'upper_quartile_salary' => 70057,
                    'average_salary' => 63059,
                    'sample_size' => 54,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $buildCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 20,
                    'experience_to' => 24,
                    'lower_quartile_salary' => 58481,
                    'median_salary' => 64912,
                    'upper_quartile_salary' => 76010,
                    'average_salary' => 66388,
                    'sample_size' => 20,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $buildCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 25,
                    'experience_to' => 29,
                    'lower_quartile_salary' => 59625,
                    'median_salary' => 65923,
                    'upper_quartile_salary' => 72177,
                    'average_salary' => 65463,
                    'sample_size' => 27,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $buildCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 30,
                    'experience_to' => 34,
                    'lower_quartile_salary' => 60587,
                    'median_salary' => 65286,
                    'upper_quartile_salary' => 71760,
                    'average_salary' => 67938,
                    'sample_size' => 18,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $buildCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 35,
                    'experience_to' => 39,
                    'lower_quartile_salary' => 52760,
                    'median_salary' => 58492,
                    'upper_quartile_salary' => 73409,
                    'average_salary' => 62599,
                    'sample_size' => 16,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $buildCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 40,
                    'experience_to' => 99, // "over 39 år"
                    'lower_quartile_salary' => 52036,
                    'median_salary' => 65349,
                    'upper_quartile_salary' => 75682,
                    'average_salary' => 65117,
                    'sample_size' => 14,
                    'statistic_year' => 2024,
                ],
            ];

            foreach ($buildWestSalaryStats as $stat) {
                ProsaSalaryStat::create($stat);
            }
        }

        // Insert Enable category salary stats for Vest for Storebælt
        if ($enableCategory && $westArea) {
            $enableWestSalaryStats = [
                [
                    'prosa_category_id' => $enableCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 0,
                    'experience_to' => 4,
                    'lower_quartile_salary' => null, // "For få besvarelser"
                    'median_salary' => null, // "For få besvarelser"
                    'upper_quartile_salary' => null, // "For få besvarelser"
                    'average_salary' => 52540,
                    'sample_size' => 4,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $enableCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 5,
                    'experience_to' => 9,
                    'lower_quartile_salary' => 39886,
                    'median_salary' => 50402,
                    'upper_quartile_salary' => 55125,
                    'average_salary' => 47588,
                    'sample_size' => 11,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $enableCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 10,
                    'experience_to' => 14,
                    'lower_quartile_salary' => 35381,
                    'median_salary' => 44976,
                    'upper_quartile_salary' => 55107,
                    'average_salary' => 47271,
                    'sample_size' => 6,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $enableCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 15,
                    'experience_to' => 19,
                    'lower_quartile_salary' => 67807,
                    'median_salary' => 76050,
                    'upper_quartile_salary' => 78625,
                    'average_salary' => 73709,
                    'sample_size' => 8,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $enableCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 20,
                    'experience_to' => 24,
                    'lower_quartile_salary' => null, // "For få besvarelser"
                    'median_salary' => null, // "For få besvarelser"
                    'upper_quartile_salary' => null, // "For få besvarelser"
                    'average_salary' => 68846,
                    'sample_size' => 3,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $enableCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 25,
                    'experience_to' => 29,
                    'lower_quartile_salary' => null, // "For få besvarelser"
                    'median_salary' => null, // "For få besvarelser"
                    'upper_quartile_salary' => null, // "For få besvarelser"
                    'average_salary' => 62630,
                    'sample_size' => 4,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $enableCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 30,
                    'experience_to' => 34,
                    'lower_quartile_salary' => null, // "For få besvarelser"
                    'median_salary' => null, // "For få besvarelser"
                    'upper_quartile_salary' => null, // "For få besvarelser"
                    'average_salary' => null, // "For få besvarelser"
                    'sample_size' => 1,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $enableCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 35,
                    'experience_to' => 39,
                    'lower_quartile_salary' => null, // "For få besvarelser"
                    'median_salary' => null, // "For få besvarelser"
                    'upper_quartile_salary' => null, // "For få besvarelser"
                    'average_salary' => null, // "For få besvarelser"
                    'sample_size' => 1,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $enableCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 40,
                    'experience_to' => 99, // "over 39 år"
                    'lower_quartile_salary' => null, // "For få besvarelser"
                    'median_salary' => null, // "For få besvarelser"
                    'upper_quartile_salary' => null, // "For få besvarelser"
                    'average_salary' => null, // "For få besvarelser"
                    'sample_size' => 1,
                    'statistic_year' => 2024,
                ],
            ];

            foreach ($enableWestSalaryStats as $stat) {
                ProsaSalaryStat::create($stat);
            }
        }

        // Insert Management category salary stats for Vest for Storebælt
        if ($managementCategory && $westArea) {
            $managementWestSalaryStats = [
                [
                    'prosa_category_id' => $managementCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 0,
                    'experience_to' => 4,
                    'lower_quartile_salary' => null, // "For få besvarelser"
                    'median_salary' => null, // "For få besvarelser"
                    'upper_quartile_salary' => null, // "For få besvarelser"
                    'average_salary' => null, // Blank/too few responses
                    'sample_size' => 2,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $managementCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 5,
                    'experience_to' => 9,
                    'lower_quartile_salary' => 44169,
                    'median_salary' => 46515,
                    'upper_quartile_salary' => 55309,
                    'average_salary' => 51036,
                    'sample_size' => 15,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $managementCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 10,
                    'experience_to' => 14,
                    'lower_quartile_salary' => 56755,
                    'median_salary' => 60087,
                    'upper_quartile_salary' => 65966,
                    'average_salary' => 60713,
                    'sample_size' => 18,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $managementCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 15,
                    'experience_to' => 19,
                    'lower_quartile_salary' => 57000,
                    'median_salary' => 64912,
                    'upper_quartile_salary' => 85050,
                    'average_salary' => 72631,
                    'sample_size' => 14,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $managementCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 20,
                    'experience_to' => 24,
                    'lower_quartile_salary' => 64235,
                    'median_salary' => 75177,
                    'upper_quartile_salary' => 84375,
                    'average_salary' => 74847,
                    'sample_size' => 10,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $managementCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 25,
                    'experience_to' => 29,
                    'lower_quartile_salary' => 58321,
                    'median_salary' => 68154,
                    'upper_quartile_salary' => 79210,
                    'average_salary' => 69184,
                    'sample_size' => 16,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $managementCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 30,
                    'experience_to' => 34,
                    'lower_quartile_salary' => null, // "For få besvarelser"
                    'median_salary' => null, // "For få besvarelser"
                    'upper_quartile_salary' => null, // "For få besvarelser"
                    'average_salary' => null, // Blank/too few responses
                    'sample_size' => 2,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $managementCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 35,
                    'experience_to' => 39,
                    'lower_quartile_salary' => null, // "For få besvarelser"
                    'median_salary' => null, // "For få besvarelser"
                    'upper_quartile_salary' => null, // "For få besvarelser"
                    'average_salary' => 66727,
                    'sample_size' => 4,
                    'statistic_year' => 2024,
                ],
                [
                    'prosa_category_id' => $managementCategory->id,
                    'prosa_area_id' => $westArea->id,
                    'experience_from' => 40,
                    'experience_to' => 99, // "over 39 år"
                    'lower_quartile_salary' => null, // "For få besvarelser"
                    'median_salary' => null, // "For få besvarelser"
                    'upper_quartile_salary' => null, // "For få besvarelser"
                    'average_salary' => null, // Blank/too few responses
                    'sample_size' => 2,
                    'statistic_year' => 2024,
                ],
            ];

            foreach ($managementWestSalaryStats as $stat) {
                ProsaSalaryStat::create($stat);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prosa_salary_stats');
    }
};
