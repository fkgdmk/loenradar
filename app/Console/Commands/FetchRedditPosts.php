<?php

namespace App\Console\Commands;

use App\Models\Payslip;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchRedditPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reddit:fetch-posts {--limit=10 : Antal posts der skal hentes} {--save : Gem posts til databasen}';

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
        $limit = $this->option('limit');
        $save = $this->option('save');
        
        $this->info("Henter de seneste {$limit} posts fra r/dkloenseddel...");

        try {
            $response = Http::withHeaders([
                'User-Agent' => config('services.reddit.user_agent', 'Laravel/1.0'),
            ])->get('https://www.reddit.com/r/dkloenseddel.json', [
                'limit' => $limit,
            ]);

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
                $this->line(sprintf(
                    "   <fg=gray>Oprettet:</> %s",
                    date('Y-m-d H:i:s', $post['created_utc'])
                ));
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
                        $payslip = Payslip::updateOrCreate(
                            [
                                'url' => 'https://reddit.com' . $post['permalink'],
                            ],
                            [
                                'title' => $post['title'],
                                'description' => $post['selftext'] ?? null,
                                'source' => 'reddit',
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
}

