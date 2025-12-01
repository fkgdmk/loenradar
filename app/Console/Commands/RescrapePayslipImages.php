<?php

namespace App\Console\Commands;

use App\Models\Payslip;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RescrapePayslipImages extends Command
{
    /**
     * OAuth access token cache
     */
    private ?string $accessToken = null;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payslips:rescrape-images 
                            {--dry-run : Vis kun hvad der ville blive gjort uden at gemme}
                            {--delay=2 : Sekunder mellem requests (rate limiting)}
                            {--limit= : Maksimalt antal payslips der skal behandles}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rescrape og download billeder fra afviste payslips der har media';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $delay = (int) $this->option('delay');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        if ($dryRun) {
            $this->warn('🔍 Dry-run mode - ingen ændringer bliver gemt');
        }

        $this->info('Henter afviste payslips med media...');

        // Hent alle payslips der:
        // 1. Har denied_at NOT NULL
        // 2. Har media tilknyttet
        // 3. Har en URL (Reddit URL)
        $query = Payslip::whereNotNull('denied_at')
            ->whereNotNull('url')
            ->whereDoesntHave('media');

        if ($limit) {
            $query->limit($limit);
        }

        $payslips = $query->get();

        if ($payslips->isEmpty()) {
            $this->warn('Ingen afviste payslips med media fundet');
            return Command::SUCCESS;
        }

        $limitInfo = $limit ? " (limit: {$limit})" : '';
        $this->info("Fandt {$payslips->count()} afviste payslips med media{$limitInfo}\n");

        $successfulIds = [];
        $failedIds = [];
        $skippedIds = [];
        $totalImagesDownloaded = 0;

        foreach ($payslips as $payslip) {
            $this->line("─────────────────────────────────────────────────────");
            $this->line(sprintf(
                "<fg=cyan>ID: %d</> - %s",
                $payslip->id,
                mb_substr($payslip->title ?? 'Uden titel', 0, 60)
            ));
            $this->line(sprintf("   <fg=gray>URL:</> %s", $payslip->url));

            // Tjek om URL'en er en Reddit URL
            if (!$this->isRedditUrl($payslip->url)) {
                $this->line("   <fg=yellow>⚠ Ikke en Reddit URL - springer over</>");
                $skippedIds[] = $payslip->id;
                continue;
            }

            try {
                // Parse Reddit URL
                $parsed = $this->parseRedditUrl($payslip->url);
                
                if (!$parsed) {
                    $this->line("   <fg=yellow>⚠ Kunne ikke parse Reddit URL</>");
                    $skippedIds[] = $payslip->id;
                    continue;
                }

                // Hent post data fra Reddit API
                $postData = $this->fetchRedditPost($parsed['subreddit'], $parsed['post_id']);
                
                if (!$postData) {
                    $this->line("   <fg=red>✗ Kunne ikke hente post fra Reddit</>");
                    $failedIds[] = $payslip->id;
                    continue;
                }

                // Extract image URLs
                $imageUrls = $this->extractImageUrls($postData);

                if (empty($imageUrls)) {
                    $this->line("   <fg=yellow>ℹ Ingen billeder fundet i post</>");
                    $skippedIds[] = $payslip->id;
                    continue;
                }

                $this->line(sprintf("   <fg=green>✓ Fandt %d billede(r)</>" , count($imageUrls)));

                $imagesDownloadedForPayslip = 0;

                foreach ($imageUrls as $imageUrl) {
                    // Tjek om billedet allerede er downloadet
                    $existingMedia = $payslip->getMedia('documents')
                        ->first(function ($media) use ($imageUrl) {
                            return $media->getCustomProperty('source_url') === $imageUrl;
                        });

                    if ($existingMedia) {
                        $this->line("   <fg=gray>ℹ Billede allerede gemt: " . basename($imageUrl) . "</>");
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("   <fg=blue>📥 [DRY-RUN] Ville downloade: " . basename($imageUrl) . "</>");
                        $imagesDownloadedForPayslip++;
                    } else {
                        try {
                            $payslip->addMediaFromUrl($imageUrl)
                                ->withCustomProperties(['source_url' => $imageUrl])
                                ->toMediaCollection('documents');
                            
                            $this->line("   <fg=green>✓ Billede downloadet: " . basename($imageUrl) . "</>");
                            $imagesDownloadedForPayslip++;
                            $totalImagesDownloaded++;
                        } catch (\Exception $e) {
                            $this->line("   <fg=yellow>⚠ Kunne ikke downloade billede: {$e->getMessage()}</>");
                        }
                    }
                }

                if ($imagesDownloadedForPayslip > 0) {
                    $successfulIds[] = $payslip->id;
                    
                    // Fjern afvisning når billede er gemt succesfuldt
                    if (!$dryRun) {
                        $payslip->update(['denied_at' => null]);
                        $this->line("   <fg=green>✓ Afvisning fjernet (denied_at = null)</>");
                    } else {
                        $this->line("   <fg=blue>🔄 [DRY-RUN] Ville fjerne afvisning</>");
                    }
                } else {
                    $skippedIds[] = $payslip->id;
                }

                // Rate limiting
                if ($delay > 0) {
                    sleep($delay);
                }

            } catch (\Exception $e) {
                $this->line("   <fg=red>✗ Fejl: {$e->getMessage()}</>");
                $failedIds[] = $payslip->id;
                
                Log::error('Fejl ved rescraping af payslip billeder', [
                    'payslip_id' => $payslip->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('✅ Rescraping afsluttet!');
        $this->info('📊 Statistik:');
        $this->info("   • Payslips behandlet: {$payslips->count()}");
        $this->info("   • Succesfulde: " . count($successfulIds));
        $this->info("   • Fejlede: " . count($failedIds));
        $this->info("   • Sprunget over: " . count($skippedIds));
        
        if (!$dryRun) {
            $this->info("   • Billeder downloadet: {$totalImagesDownloaded}");
        }

        if (!empty($successfulIds)) {
            $this->newLine();
            $this->info('🎉 Payslip ID\'er med nye billeder:');
            $this->line('   ' . implode(', ', $successfulIds));
        }

        if (!empty($failedIds)) {
            $this->newLine();
            $this->warn('❌ Fejlede Payslip ID\'er:');
            $this->line('   ' . implode(', ', $failedIds));
        }

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        Log::info('Payslip billede rescraping afsluttet', [
            'total' => $payslips->count(),
            'successful_ids' => $successfulIds,
            'failed_ids' => $failedIds,
            'skipped_ids' => $skippedIds,
            'images_downloaded' => $totalImagesDownloaded,
        ]);

        return Command::SUCCESS;
    }

    /**
     * Check if URL is a Reddit URL
     */
    private function isRedditUrl(string $url): bool
    {
        return str_contains($url, 'reddit.com');
    }

    /**
     * Parse Reddit URL to extract subreddit and post ID
     */
    private function parseRedditUrl(string $url): ?array
    {
        // Remove .json suffix if present
        $url = preg_replace('/\.json$/', '', $url);
        
        // Pattern: https://www.reddit.com/r/{subreddit}/comments/{post_id}/...
        $pattern = '#https?://(?:www\.)?reddit\.com/r/([^/]+)/comments/([^/]+)#';
        
        if (preg_match($pattern, $url, $matches)) {
            return [
                'subreddit' => $matches[1],
                'post_id' => $matches[2],
            ];
        }
        
        return null;
    }

    /**
     * Fetch a specific Reddit post
     */
    private function fetchRedditPost(string $subreddit, string $postId): ?array
    {
        try {
            $apiUrl = "https://oauth.reddit.com/r/{$subreddit}/comments/{$postId}/";
            $response = $this->makeAuthenticatedRequest($apiUrl);

            $this->handleRateLimit($response);

            if ($response->failed()) {
                return null;
            }

            $data = $response->json();

            // Reddit API returnerer et array med 2 elementer:
            // [0] = post data, [1] = comments data
            if (!isset($data[0]['data']['children'][0]['data'])) {
                return null;
            }

            return $data[0]['data']['children'][0]['data'];

        } catch (\Exception $e) {
            Log::warning('Kunne ikke hente Reddit post', [
                'subreddit' => $subreddit,
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get OAuth access token
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $response = Http::asForm()
            ->withBasicAuth(
                config('services.reddit.client_id'),
                config('services.reddit.client_secret')
            )
            ->post('https://www.reddit.com/api/v1/access_token', [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            throw new \Exception("Kunne ikke hente OAuth token: " . $response->status());
        }

        $data = $response->json();
        $this->accessToken = $data['access_token'];

        $this->info("🔐 OAuth token hentet succesfuldt");

        return $this->accessToken;
    }

    /**
     * Make authenticated API request
     */
    private function makeAuthenticatedRequest(string $url, array $params = []): \Illuminate\Http\Client\Response
    {
        $token = $this->getAccessToken();
        return Http::withHeaders([
            'User-Agent' => config('services.reddit.user_agent'),
            'Authorization' => "Bearer {$token}",
        ])->get($url, $params);
    }

    /**
     * Check and handle rate limits
     */
    private function handleRateLimit(\Illuminate\Http\Client\Response $response): void
    {
        $remaining = $response->header('X-Ratelimit-Remaining');
        $reset = $response->header('X-Ratelimit-Reset');
        
        if ($remaining !== null && (int)$remaining < 10) {
            $this->warn("⚠️  Rate limit: Kun {$remaining} requests tilbage");
            if ($reset !== null) {
                $waitTime = (int)$reset;
                $this->info("   Venter {$waitTime} sekunder før reset...");
                sleep($waitTime);
            }
        }
    }

    /**
     * Check if URL points to an image
     */
    private function isImageUrl(string $url): bool
    {
        // Tjek om URL'en ender med et billede filtype
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        
        if (in_array($extension, $imageExtensions)) {
            return true;
        }
        
        // Tjek om det er fra Reddit eller Imgur billede domæner
        $imageDomains = ['i.redd.it', 'i.imgur.com', 'imgur.com', 'preview.redd.it'];
        $host = parse_url($url, PHP_URL_HOST);
        
        return in_array($host, $imageDomains);
    }

    /**
     * Extract image URLs from a Reddit post
     * Handles both url_overridden_by_dest and media_metadata formats
     * 
     * @return array<string> Array of image URLs
     */
    private function extractImageUrls(array $post): array
    {
        $imageUrls = [];

        // Metode 1: Direkte billede link i url_overridden_by_dest
        if (!empty($post['url_overridden_by_dest'])) {
            $url = $post['url_overridden_by_dest'];
            if ($this->isImageUrl($url)) {
                $imageUrls[] = $url;
            }
        }

        // Metode 2: Billeder i media_metadata (nyere Reddit posts)
        if (!empty($post['media_metadata']) && is_array($post['media_metadata'])) {
            foreach ($post['media_metadata'] as $mediaId => $media) {
                // Tjek at det er et gyldigt billede
                if (!isset($media['status']) || $media['status'] !== 'valid') {
                    continue;
                }
                
                if (!isset($media['e']) || $media['e'] !== 'Image') {
                    continue;
                }

                // Hent den fulde størrelse billede URL fra 's' (source)
                if (isset($media['s']['u'])) {
                    // Reddit returnerer HTML-encoded URLs, så decode dem
                    $url = html_entity_decode($media['s']['u']);
                    $imageUrls[] = $url;
                }
            }
        }

        // Metode 3: Gallery posts
        if (!empty($post['is_gallery']) && !empty($post['gallery_data']['items'])) {
            foreach ($post['gallery_data']['items'] as $item) {
                $mediaId = $item['media_id'] ?? null;
                if ($mediaId && isset($post['media_metadata'][$mediaId]['s']['u'])) {
                    $url = html_entity_decode($post['media_metadata'][$mediaId]['s']['u']);
                    $imageUrls[] = $url;
                }
            }
        }

        return array_unique($imageUrls);
    }
}

