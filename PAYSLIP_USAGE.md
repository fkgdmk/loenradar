# Payslip Model - Brugseksempler

## 📋 Oversigt

Payslip modellen bruges til at gemme lønsedler og relateret information. Modellen understøtter dokumenthåndtering gennem Spatie Media Library.

## 🔧 Model Felter

- `title` (string, nullable) - Titel på lønsedlen
- `description` (text, nullable) - Beskrivelse
- `url` (string, nullable) - Link til kilde
- `job_titel` (string, nullable) - Job titel
- `source` (string, nullable) - Kilde (fx 'reddit', 'manual', etc.)
- `uploaded_at` (timestamp, nullable) - Hvornår lønsedlen blev uploadet til den originale platform (fx Reddit)
- `salary` (decimal, nullable) - Grundløn/basisløn i DKK uden tillæg (ekstrakt via OpenAI)
- `verified_at` (timestamp, nullable) - Hvornår lønsedlen blev verificeret manuelt
- `experience` (integer, nullable) - Antal års erhvervserfaring
- `timestamps` - Laravel timestamps (created_at, updated_at)

## 💾 Opret en Payslip

```php
use App\Models\Payslip;

// Simpel oprettelse
$payslip = Payslip::create([
    'title' => 'Software Engineer - København',
    'description' => 'Lønseddel for software engineer position',
    'url' => 'https://reddit.com/r/dkloenseddel/post123',
    'job_titel' => 'Software Engineer',
    'source' => 'reddit',
    'salary' => 45000.00,
    'experience' => 3, // 3 års erfaring
]);

// Eller med updateOrCreate (undgå dubletter)
$payslip = Payslip::updateOrCreate(
    ['url' => 'https://reddit.com/r/dkloenseddel/post123'],
    [
        'title' => 'Software Engineer - København',
        'description' => 'Opdateret beskrivelse',
        'job_titel' => 'Software Engineer',
        'source' => 'reddit',
    ]
);
```

## 📎 Arbejde med Dokumenter

### Upload et Dokument

```php
// Fra en fil path
$payslip->addMedia('/path/to/document.pdf')
    ->toMediaCollection('documents');

// Fra en upload (i en controller)
$payslip->addMediaFromRequest('document')
    ->toMediaCollection('documents');

// Fra en URL
$payslip->addMediaFromUrl('https://example.com/document.pdf')
    ->toMediaCollection('documents');

// Med custom navn og metadata
$payslip->addMedia('/path/to/document.pdf')
    ->usingName('Min Lønseddel 2024')
    ->withCustomProperties(['month' => 'Januar', 'year' => 2024])
    ->toMediaCollection('documents');
```

### Hent Dokumenter

```php
// Hent alle dokumenter
$documents = $payslip->getMedia('documents');

// Hent det første dokument
$document = $payslip->getFirstMedia('documents');

// Få URL til dokumentet
$url = $document->getUrl();

// Få fil path
$path = $document->getPath();

// Check om der er dokumenter
if ($payslip->hasMedia('documents')) {
    // Der er dokumenter
}
```

### Slet Dokumenter

```php
// Slet et specifikt dokument (via ID)
$document = $payslip->getFirstMedia('documents');
$document->delete();

// Slet alle dokumenter i en collection
$payslip->clearMediaCollection('documents');
```

## ✅ Verificering

```php
// Marker som verificeret
$payslip->markAsVerified();

// Fjern verificering
$payslip->unverify();

// Tjek om verificeret
if ($payslip->isVerified()) {
    echo "Denne lønseddel er verificeret";
}

// Manuel verificering med tidspunkt
$payslip->update(['verified_at' => now()]);
```

## 🔍 Query Eksempler

```php
// Find alle payslips fra Reddit
$redditPayslips = Payslip::where('source', 'reddit')->get();

// Find payslips med dokumenter
$payslipsWithDocs = Payslip::has('media')->get();

// Søg i titel
$results = Payslip::where('title', 'like', '%Software Engineer%')->get();

// Nyeste først
$latest = Payslip::latest()->take(10)->get();

// Med eager loading af media
$payslips = Payslip::with('media')->get();

// Find kun verificerede payslips
$verified = Payslip::whereNotNull('verified_at')->get();

// Find uverificerede payslips
$unverified = Payslip::whereNull('verified_at')->get();

// Find payslips verificeret i dag
$todayVerified = Payslip::whereDate('verified_at', today())->get();
```

## 🎯 Controller Eksempel

```php
namespace App\Http\Controllers;

use App\Models\Payslip;
use Illuminate\Http\Request;

class PayslipController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'url' => 'nullable|url',
            'job_titel' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:255',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240', // 10MB max
        ]);

        $payslip = Payslip::create($validated);

        // Upload dokument hvis det findes
        if ($request->hasFile('document')) {
            $payslip->addMediaFromRequest('document')
                ->toMediaCollection('documents');
        }

        return response()->json([
            'message' => 'Payslip oprettet succesfuldt',
            'payslip' => $payslip->load('media'),
        ], 201);
    }

    public function show(Payslip $payslip)
    {
        return response()->json([
            'payslip' => $payslip->load('media'),
            'documents' => $payslip->getMedia('documents')->map(function ($media) {
                return [
                    'id' => $media->id,
                    'name' => $media->name,
                    'file_name' => $media->file_name,
                    'url' => $media->getUrl(),
                    'size' => $media->size,
                    'mime_type' => $media->mime_type,
                ];
            }),
        ]);
    }
}
```

## 🚀 Reddit Command Usage

Hent posts fra Reddit og gem dem til databasen:

### Standard Mode (Enkelt Request)

```bash
# Hent og vis posts (gem IKKE)
php artisan reddit:fetch-posts

# Hent og GEM posts til databasen
php artisan reddit:fetch-posts --save

# Hent de sidste 25 posts og gem dem
php artisan reddit:fetch-posts --limit=25 --save
```

### Bulk Import Mode (Med Pagination)

```bash
# Hent de sidste 1000 posts med pagination
php artisan reddit:fetch-posts --bulk --save

# Hent et custom antal (max ~1000 pga Reddit begrænsning)
php artisan reddit:fetch-posts --bulk --save --bulk-limit=500

# Juster rate limiting delay (standard 2 sekunder)
php artisan reddit:fetch-posts --bulk --save --delay=3

# Kun preview uden at gemme
php artisan reddit:fetch-posts --bulk
```

**Bulk Import Features:**
- ✅ Automatisk pagination gennem Reddit API
- ✅ Rate limiting (2 sek mellem requests som standard)
- ✅ Real-time statistik og progress
- ✅ Håndterer Reddit's 'after' parameter
- ✅ Respekterer API begrænsninger (max ~1000 posts)
- ✅ Automatisk billede download
- ✅ Duplikat beskyttelse

## 📚 Spatie Media Library Features

- ✅ Upload filer fra forskellige kilder (disk, URL, request)
- ✅ Organiser filer i collections
- ✅ Tilføj custom metadata til filer
- ✅ Automatisk oprydning ved sletning af model
- ✅ Understøtter mange filtyper (PDF, billeder, dokumenter, etc.)
- ✅ Responsive images (hvis du konfigurerer det)
- ✅ Custom disks (public, s3, etc.)

## 🔐 Storage Configuration

Media filer gemmes som standard på `public` disk. Du kan ændre dette i `config/media-library.php` eller i model:

```php
public function registerMediaCollections(): void
{
    $this->addMediaCollection('documents')
        ->useDisk('s3'); // Brug S3 i stedet for public
}
```

## 📖 Yderligere Information

- [Spatie Media Library Dokumentation](https://spatie.be/docs/laravel-medialibrary)
- Laravel Storage: [https://laravel.com/docs/filesystem](https://laravel.com/docs/filesystem)

