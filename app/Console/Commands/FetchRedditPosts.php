<?php

namespace App\Console\Commands;

use App\Models\Payslip;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchRedditPosts extends Command
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
    protected $signature = 'reddit:fetch-posts 
                            {--limit=10 : Antal posts der skal hentes} 
                            {--save : Gem posts til databasen}
                            {--bulk : Hent posts i bulk med pagination (ignorer limit)}
                            {--bulk-limit=1000 : Antal posts ved bulk import}
                            {--delay=5 : Sekunder mellem requests (rate limiting)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hent de seneste posts fra r/dkloenseddel';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $bulk = $this->option('bulk');
        $save = $this->option('save');
        $delay = (int) $this->option('delay');

        if ($bulk) {
            return $this->handleBulkImport($save, $delay);
        }

        // Standard enkelt request
        $limit = $this->option('limit');
        $this->info("Henter de seneste {$limit} posts fra r/dkloenseddel...");

        try {
            $response = $this->makeAuthenticatedRequest(
                'https://oauth.reddit.com/r/dkloenseddel',
                ['limit' => $limit]
            );

            $this->handleRateLimit($response);

            if ($response->failed()) {
                $this->error('Kunne ikke hente posts fra Reddit API');
                $this->error("Status: {$response->status()}");
                return Command::FAILURE;
            }

            $data = $response->json();
            $posts = $data['data']['children'] ?? [];

            if (empty($posts)) {
                $this->warn('Ingen posts fundet');
                return Command::SUCCESS;
            }

            $this->info("Fandt {$this->count($posts)} posts:\n");

            $savedCount = 0;

            foreach ($posts as $index => $item) {
                $post = $item['data'];
                
                $this->line('─────────────────────────────────────────────────────');
                $this->line(sprintf(
                    "<fg=cyan>%d. %s</>",
                    $index + 1,
                    $post['title']
                ));
                $this->line(sprintf(
                    "   <fg=gray>Forfatter:</> %s | <fg=gray>Score:</> %d | <fg=gray>Kommentarer:</> %d",
                    $post['author'],
                    $post['score'],
                    $post['num_comments']
                ));
                
                if (isset($post['created_utc'])) {
                    $uploadedAt = \Carbon\Carbon::createFromTimestamp($post['created_utc']);
                    $this->line(sprintf(
                        "   <fg=gray>Uploadet:</> %s (%s)",
                        $uploadedAt->format('Y-m-d H:i:s'),
                        $uploadedAt->diffForHumans()
                    ));
                }
                $this->line(sprintf(
                    "   <fg=gray>URL:</> https://reddit.com%s",
                    $post['permalink']
                ));
                
                if (!empty($post['selftext'])) {
                    $preview = mb_substr($post['selftext'], 0, 100);
                    if (mb_strlen($post['selftext']) > 100) {
                        $preview .= '...';
                    }
                    $this->line(sprintf(
                        "   <fg=gray>Tekst:</> %s",
                        $preview
                    ));
                }

                if (!empty($post['url']) && !str_contains($post['url'], 'reddit.com')) {
                    $this->line(sprintf(
                        "   <fg=gray>Link:</> %s",
                        $post['url']
                    ));
                }

                // Gem til database hvis --save flag er sat
                if ($save) {
                    try {
                        // Hent forfatterens kommentarer
                        $authorComments = $this->fetchAuthorComments($post);

                        $payslip = Payslip::updateOrCreate(
                            [
                                'url' => 'https://reddit.com' . $post['permalink'],
                            ],
                            [
                                'title' => $post['title'],
                                'description' => $post['selftext'] ?? null,
                                'comments' => $authorComments,
                                'source' => 'reddit',
                                'uploaded_at' => isset($post['created_utc']) 
                                    ? \Carbon\Carbon::createFromTimestamp($post['created_utc'])
                                    : null,
                                // job_title_id opdateres via payslips:extract-job-titles command
                            ]
                        );
                        
                        $savedCount++;
                        $this->line("   <fg=green>✓ Gemt til database (ID: {$payslip->id})</>");

                        // Download og gem billede hvis det findes og ikke allerede er gemt
                        if (!empty($post['url_overridden_by_dest'])) {
                            $imageUrl = $post['url_overridden_by_dest'];
                            
                            // Tjek om det er et billede (reddit billeder eller imgur)
                            if ($this->isImageUrl($imageUrl)) {
                                // Tjek om billedet allerede er downloadet
                                $existingMedia = $payslip->getMedia('documents')
                                    ->first(function ($media) use ($imageUrl) {
                                        return $media->getCustomProperty('source_url') === $imageUrl;
                                    });

                                if ($existingMedia) {
                                    $this->line("   <fg=gray>ℹ Billede allerede gemt</>");
                                } else {
                                    try {
                                        $payslip->addMediaFromUrl($imageUrl)
                                            ->withCustomProperties(['source_url' => $imageUrl])
                                            ->toMediaCollection('documents');
                                        
                                        $this->line("   <fg=green>✓ Billede downloadet og gemt</>");
                                    } catch (\Exception $e) {
                                        $this->line("   <fg=yellow>⚠ Kunne ikke downloade billede: {$e->getMessage()}</>");
                                    }
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        $this->line("   <fg=red>✗ Kunne ikke gemme: {$e->getMessage()}</>");
                    }
                }
            }

            $this->newLine();
            $this->info('✓ Posts hentet succesfuldt');

            if ($save && $savedCount > 0) {
                $this->info("✓ {$savedCount} posts gemt til databasen");
            }

            // Log til Laravel log
            Log::info('Reddit posts hentet', [
                'subreddit' => 'dkloenseddel',
                'count' => count($posts),
                'limit' => $limit,
                'saved' => $savedCount ?? 0,
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Der opstod en fejl ved hentning af Reddit posts');
            $this->error($e->getMessage());
            
            Log::error('Fejl ved hentning af Reddit posts', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
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

        try {
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
        } catch (\Exception $e) {
            $this->error("Fejl ved OAuth authentication: {$e->getMessage()}");
            throw $e;
        }
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
     * Handle bulk import with pagination
     */
    private function handleBulkImport(bool $save, int $delay): int
    {
        $bulkLimit = (int) $this->option('bulk-limit');
        $perPage = 100; // Reddit max per request
        
        $this->info("🚀 Bulk import: Henter op til {$bulkLimit} posts fra r/dkloenseddel");
        $this->info("Rate limiting: {$delay} sekunder mellem requests\n");

        if (!$save) {
            $this->warn('⚠️  Advarsel: --save flag er ikke sat. Posts bliver IKKE gemt til databasen!');
            $this->newLine();
        }

        $totalFetched = 0;
        $totalSaved = 0;
        $totalImages = 0;
        $after = null;
        $page = 1;

        try {
            while ($totalFetched < $bulkLimit) {
                $this->info("📄 Side {$page} - Henter...");

                $response = $this->makeAuthenticatedRequest(
                    'https://oauth.reddit.com/r/dkloenseddel',
                    [
                        'limit' => min($perPage, $bulkLimit - $totalFetched),
                        'after' => $after,
                    ]
                );

                $this->handleRateLimit($response);

                if ($response->failed()) {
                    $this->error("Fejl ved hentning af side {$page}: Status {$response->status()}");
                    break;
                }

                $data = $response->json();
                $posts = $data['data']['children'] ?? [];
                $after = $data['data']['after'] ?? null;

                if (empty($posts)) {
                    $this->warn('Ingen flere posts fundet');
                    break;
                }

                $this->info("   Fandt " . count($posts) . " posts");

                // Process posts
                foreach ($posts as $item) {
                    $result = $this->processPost($item['data'], $save);
                    
                    if ($result['saved']) {
                        $totalSaved++;
                    }
                    if ($result['image_downloaded']) {
                        $totalImages++;
                    }
                }

                $totalFetched += count($posts);
                $this->info("   ✓ Processeret: {$totalFetched}/{$bulkLimit} posts");

                // Stop hvis der ikke er flere sider
                if ($after === null) {
                    $this->info('📭 Ingen flere posts tilgængelige');
                    break;
                }

                // Rate limiting - vent mellem requests
                if ($totalFetched < $bulkLimit && $after !== null) {
                    $this->info("   ⏳ Venter {$delay} sekunder (rate limiting)...\n");
                    sleep($delay);
                }

                $page++;
            }

            $this->newLine();
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info("✅ Bulk import afsluttet!");
            $this->info("📊 Statistik:");
            $this->info("   • Posts hentet: {$totalFetched}");
            if ($save) {
                $this->info("   • Posts gemt: {$totalSaved}");
                $this->info("   • Billeder downloadet: {$totalImages}");
            }
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            Log::info('Reddit bulk import afsluttet', [
                'total_fetched' => $totalFetched,
                'total_saved' => $totalSaved,
                'total_images' => $totalImages,
                'pages' => $page,
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Der opstod en kritisk fejl under bulk import');
            $this->error($e->getMessage());
            
            Log::error('Fejl ved bulk import', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Process a single post
     */
    private function processPost(array $post, bool $save): array
    {
        $result = [
            'saved' => false,
            'image_downloaded' => false,
        ];

        if (!$save) {
            return $result;
        }

        try {
            // Hent forfatterens kommentarer
            $authorComments = $this->fetchAuthorComments($post);

            $payslip = Payslip::updateOrCreate(
                [
                    'url' => 'https://reddit.com' . $post['permalink'],
                ],
                [
                    'title' => $post['title'],
                    'description' => $post['selftext'] ?? null,
                    'comments' => $authorComments,
                    'source' => 'reddit',
                    'uploaded_at' => isset($post['created_utc']) 
                        ? \Carbon\Carbon::createFromTimestamp($post['created_utc'])
                        : null,
                    // job_title_id opdateres via payslips:extract-job-titles command
                ]
            );
            
            $result['saved'] = true;

            // Download og gem billede hvis det findes og ikke allerede er gemt
            if (!empty($post['url_overridden_by_dest'])) {
                $imageUrl = $post['url_overridden_by_dest'];
                
                // Tjek om det er et billede (reddit billeder eller imgur)
                if ($this->isImageUrl($imageUrl)) {
                    // Tjek om billedet allerede er downloadet
                    $existingMedia = $payslip->getMedia('documents')
                        ->first(function ($media) use ($imageUrl) {
                            return $media->getCustomProperty('source_url') === $imageUrl;
                        });

                    if (!$existingMedia) {
                        try {
                            $payslip->addMediaFromUrl($imageUrl)
                                ->withCustomProperties(['source_url' => $imageUrl])
                                ->toMediaCollection('documents');
                            
                            $result['image_downloaded'] = true;
                        } catch (\Exception $e) {
                            // Ignorér billede fejl i bulk mode
                            Log::warning('Kunne ikke downloade billede', [
                                'url' => $imageUrl,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Kunne ikke gemme post', [
                'title' => $post['title'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Count the number of posts
     */
    private function count(array $posts): int
    {
        return count($posts);
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
        $imageDomains = ['i.redd.it', 'i.imgur.com', 'imgur.com'];
        $host = parse_url($url, PHP_URL_HOST);
        
        return in_array($host, $imageDomains);
    }

    /**
     * Fetch author comments from a Reddit post
     */
    private function fetchAuthorComments(array $post): ?array
    {
        try {
            // Hent post ID fra permalink
            $permalink = $post['permalink'];
            $author = $post['author'];

            // Kald Reddit API for at hente kommentarer
            $response = $this->makeAuthenticatedRequest("https://oauth.reddit.com{$permalink}");

            $this->handleRateLimit($response);

            if ($response->failed()) {
                Log::warning('Kunne ikke hente kommentarer fra Reddit', [
                    'permalink' => $permalink,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $data = $response->json();
            
            // Reddit API returnerer et array med 2 elementer:
            // [0] = post data, [1] = comments data
            if (!isset($data[1]['data']['children'])) {
                return null;
            }

            $comments = [];
            
            // Gennemgå alle kommentarer rekursivt
            $this->extractAuthorComments($data[1]['data']['children'], $author, $comments);

            if (empty($comments)) {
                return null;
            }

            // Returner kommentarerne som array
            return $comments;

        } catch (\Exception $e) {
            Log::warning('Fejl ved hentning af forfatterens kommentarer', [
                'error' => $e->getMessage(),
                'post_title' => $post['title'] ?? 'unknown',
            ]);
            return null;
        }
    }

    /**
     * Recursively extract author comments from comment tree
     */
    private function extractAuthorComments(array $children, string $author, array &$comments): void
    {
        foreach ($children as $child) {
            // Spring over hvis det ikke er en kommentar
            if (!isset($child['data']) || $child['kind'] !== 't1') {
                continue;
            }

            $commentData = $child['data'];

            // Tjek om kommentaren er fra forfatteren
            if (isset($commentData['author']) && $commentData['author'] === $author) {
                // Tilføj kommentarteksten
                if (!empty($commentData['body'])) {
                    $comments[] = $commentData['body'];
                }
            }

            // Gennemgå svar rekursivt
            if (isset($commentData['replies']['data']['children'])) {
                $this->extractAuthorComments($commentData['replies']['data']['children'], $author, $comments);
            }
        }
    }
}

