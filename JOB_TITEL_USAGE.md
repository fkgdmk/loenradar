# JobTitel Model - Brugseksempler

## 📋 Oversigt

JobTitel modellen bruges til at normalisere og kategorisere job titler på tværs af alle lønsedler. Dette gør det muligt at sammenligne lønninger for samme stillings type.

## 🔧 Model Struktur

### JobTitel Table
- `id` (bigint, primary key)
- `name` (string, unique) - Normaliseret job titel
- `timestamps` (created_at, updated_at)

### Payslip Relation
- `job_titel_id` (foreign key) - Reference til JobTitel
- Relation: `belongsTo(JobTitel::class)`

## 💾 Arbejde med JobTitel

### Opret eller find job titel

```php
use App\Models\JobTitel;

// Find eller opret (recommended)
$jobTitel = JobTitel::firstOrCreate(['name' => 'Software Engineer']);

// Direkte oprettelse
$jobTitel = JobTitel::create(['name' => 'Data Scientist']);

// Find eksisterende
$jobTitel = JobTitel::where('name', 'Læge')->first();
```

### Tilknyt til Payslip

```php
use App\Models\Payslip;
use App\Models\JobTitel;

$payslip = Payslip::find(1);
$jobTitel = JobTitel::firstOrCreate(['name' => 'Software Engineer']);

// Tilknyt via relationship
$payslip->jobTitel()->associate($jobTitel);
$payslip->save();

// Eller direkte via update
$payslip->update(['job_titel_id' => $jobTitel->id]);
```

### Hent payslips for en job titel

```php
$jobTitel = JobTitel::where('name', 'Software Engineer')->first();

// Alle payslips med denne job titel
$payslips = $jobTitel->payslips;

// Med eager loading
$payslips = $jobTitel->payslips()->with('media')->get();

// Gennemsnitsløn for job titel
$avgSalary = $jobTitel->payslips()
    ->whereNotNull('salary')
    ->avg('salary');

echo "Gennemsnitsløn for {$jobTitel->name}: " . number_format($avgSalary, 2, ',', '.') . " DKK";
```

## 🤖 Automatisk Ekstraktion med OpenAI

### Kommando Usage

```bash
# Ekstrahér job titler for alle payslips uden job titel
php artisan payslips:extract-job-titles

# Vis kun omkostningsestimat
php artisan payslips:extract-job-titles --estimate

# Ekstrahér for de første 10 payslips
php artisan payslips:extract-job-titles --limit=10

# Genekstrahér alle (inkl. dem med job titel)
php artisan payslips:extract-job-titles --force

# Ekstrahér for et specifikt payslip
php artisan payslips:extract-job-titles --id=123
```

### Eksempel Output

```
🔍 Ekstraherer job titler fra payslips med OpenAI...

Fandt 50 payslip(s) til ekstraktion

💰 Omkostningsestimat:
   Antal payslips: 50
   Estimeret pris: $0.0025 USD (~0.02 DKK)
   Model: gpt-4o-mini

 50/50 [============================] 100%

✓ Payslip #1: Software Engineer - København
  Job titel: Software Engineer

✓ Payslip #2: Psykolog beskæftigelsesområdet
  Job titel: Psykolog

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ Ekstraktion afsluttet!
📊 Resultat:
   • Total processeret: 50
   • Succesfulde: 48
   • Fejlede: 2
   • Estimeret omkostning: $0.0025 USD
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

### Programmatisk Brug

```php
use App\Models\Payslip;
use App\Services\JobTitelExtractor;

$payslip = Payslip::find(1);
$extractor = new JobTitelExtractor();

// Ekstrahér job titel
$jobTitel = $extractor->extractJobTitle($payslip);

if ($jobTitel) {
    echo "Job titel: {$jobTitel->name}";
}

// Beregn omkostning for batch
$cost = $extractor->estimateCost(100);
echo "100 payslips koster: \${$cost['estimated_cost_usd']} USD";
```

## 📊 Query Eksempler

### Find mest populære job titler

```php
use App\Models\JobTitel;
use Illuminate\Support\Facades\DB;

$topJobTitles = JobTitel::withCount('payslips')
    ->orderBy('payslips_count', 'desc')
    ->take(10)
    ->get();

foreach ($topJobTitles as $jobTitel) {
    echo "{$jobTitel->name}: {$jobTitel->payslips_count} lønsedler\n";
}
```

### Gennemsnitsløn per job titel

```php
$salaryStats = JobTitel::with('payslips')
    ->get()
    ->map(function ($jobTitel) {
        $avgSalary = $jobTitel->payslips()
            ->whereNotNull('salary')
            ->avg('salary');
        
        return [
            'job_title' => $jobTitel->name,
            'avg_salary' => $avgSalary,
            'count' => $jobTitel->payslips()->whereNotNull('salary')->count(),
        ];
    })
    ->filter(fn($item) => $item['count'] > 0)
    ->sortByDesc('avg_salary');

foreach ($salaryStats as $stat) {
    echo "{$stat['job_title']}: " . 
         number_format($stat['avg_salary'], 0, ',', '.') . 
         " DKK ({$stat['count']} lønsedler)\n";
}
```

### Find job titler inden for løninterval

```php
use App\Models\JobTitel;

$highPayingJobs = JobTitel::whereHas('payslips', function ($query) {
    $query->where('salary', '>=', 50000);
})->get();

$entryLevelJobs = JobTitel::whereHas('payslips', function ($query) {
    $query->where('salary', '<=', 35000);
})->get();
```

### Sammenlign løn med erfaring

```php
$jobTitel = JobTitel::where('name', 'Software Engineer')->first();

$salaryByExperience = $jobTitel->payslips()
    ->whereNotNull('salary')
    ->whereNotNull('experience')
    ->selectRaw('experience, AVG(salary) as avg_salary, COUNT(*) as count')
    ->groupBy('experience')
    ->orderBy('experience')
    ->get();

foreach ($salaryByExperience as $level) {
    echo "Erfaring: {$level->experience} år - " .
         "Løn: " . number_format($level->avg_salary, 0, ',', '.') . " DKK " .
         "({$level->count} personer)\n";
}
```

## 💰 Omkostninger

### gpt-4o-mini Pricing
- Input: $0.15 per 1M tokens
- Output: $0.60 per 1M tokens

### Estimeret pris per job titel ekstraktion
- ~$0.00005 USD (0.0004 DKK) per payslip
- Cirka **20x billigere** end salary ekstraktion (kun tekst, ingen billeder)

### Eksempler

| Antal payslips | Pris USD | Pris DKK |
|----------------|----------|----------|
| 10             | $0.0005  | 0.004 kr |
| 100            | $0.005   | 0.04 kr  |
| 1000           | $0.05    | 0.35 kr  |
| 10000          | $0.50    | 3.50 kr  |

## 🎯 Workflow Anbefaling

### 1. Importer fra Reddit
```bash
php artisan reddit:fetch-posts --bulk --save --bulk-limit=1000
```

### 2. Ekstrahér job titler
```bash
php artisan payslips:extract-job-titles
```

### 3. Analysér lønsedler (kun dem med billeder)
```bash
php artisan payslips:analyze
```

### 4. Query og analyser data
```php
// Gennemsnitsløn per job titel
$avgSalaries = JobTitel::with('payslips')
    ->get()
    ->map(function ($jobTitel) {
        return [
            'name' => $jobTitel->name,
            'avg_salary' => $jobTitel->payslips()->avg('salary'),
            'count' => $jobTitel->payslips()->count(),
        ];
    })
    ->sortByDesc('avg_salary');
```

## 📈 Fordele ved JobTitel Model

✅ **Normalisering** - "Software Engineer" vs "software engineer" bliver samme titel  
✅ **Deduplicering** - Kun én record per unik job titel  
✅ **Performance** - Hurtigere queries med indexed foreign keys  
✅ **Skalerbarhed** - Let at tilføje metadata til job titler senere  
✅ **Analytics** - Nem aggregering og statistik per job titel  
✅ **Data quality** - Konsistente job titler på tværs af systemet  

## 🔄 Migration fra gamle data

Hvis du har gamle payslips med `job_titel` string i stedet:

```php
// Script til at migrere gamle data (run once)
use App\Models\Payslip;
use App\Models\JobTitel;

Payslip::whereNull('job_titel_id')
    ->whereNotNull('title')
    ->chunk(100, function ($payslips) {
        foreach ($payslips as $payslip) {
            $extractor = new \App\Services\JobTitelExtractor();
            $extractor->extractJobTitle($payslip);
        }
    });
```

## 🛠️ Avanceret: Custom Mapping

Du kan manuelt mappe specifikke variationer til samme job titel:

```php
// Mapping array
$mapping = [
    'Software Developer' => 'Software Engineer',
    'Programmør' => 'Software Engineer',
    'Full Stack Developer' => 'Software Engineer',
];

foreach ($mapping as $from => $to) {
    $fromJob = JobTitel::where('name', $from)->first();
    $toJob = JobTitel::firstOrCreate(['name' => $to]);
    
    if ($fromJob) {
        // Opdater alle payslips
        $fromJob->payslips()->update(['job_titel_id' => $toJob->id]);
        
        // Slet den gamle titel
        $fromJob->delete();
    }
}
```

## 📚 Yderligere Information

- OpenAI bruges til intelligent ekstraktion af job titler
- Systemet normaliserer automatisk (trim, fjerner "job som", etc.)
- Duplikater undgås via `unique` constraint på `name`
- Foreign key med `nullOnDelete` sikrer data integritet

