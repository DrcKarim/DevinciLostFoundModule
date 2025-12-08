<?php
namespace DescriptionWithAI\Service;

class MatchingService
{
    private $textAiService;
    private $api;
    private $logger;

    public function __construct($textAiService, $api, $logger)
    {
        $this->textAiService = $textAiService;
        $this->api = $api;
        $this->logger = $logger;
    }

    /**
     * Find matching found objects for a lost item description
     * Uses optimized one-by-one AI comparison with fallback to random suggestion
     */
    public function findMatchingObjects($lostTitle, $lostDescription)
    {
        $this->logger->info("Searching for matches: title='$lostTitle', desc='$lostDescription'");

        // Get all found objects (items in Omeka-S) - limit to recent 10 items for performance
        $foundItems = $this->api->search('items', ['sort_by' => 'created', 'sort_order' => 'desc', 'limit' => 10])->getContent();

        if (empty($foundItems)) {
            $this->logger->warn("No items found in database");
            return [];
        }

        $bestMatch = null;
        $bestScore = 0;
        $aiFailedCount = 0;
        $threshold = 50; // Minimum score threshold (50%)

        // Compare items one by one for better performance
        foreach ($foundItems as $item) {
            $foundDesc = $item->value('dcterms:description');
            if (!$foundDesc) continue;

            $foundTitle = $item->value('dcterms:title');
            $foundText = $foundDesc->value();
            $foundTitleText = $foundTitle ? $foundTitle->value() : '';

            // Calculate similarity using AI (returns 0-100 score or false on failure)
            $score = $this->compareItemWithAI($lostTitle, $lostDescription, $foundTitleText, $foundText);

            // Track AI failures
            if ($score === false) {
                $aiFailedCount++;
                continue;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $item;
            }

            // Early exit if we find a very strong match (90+)
            if ($score >= 90) {
                $this->logger->info("Found strong match (score: $score) - stopping search");
                break;
            }
        }

        // If AI completely failed, pick random item
        if ($aiFailedCount >= count($foundItems)) {
            $this->logger->warn("AI failed for all items. Selecting random suggestion");
            $randomItem = $foundItems[array_rand(iterator_to_array($foundItems))];
            return [$this->formatMatchResult($randomItem, 25, '⚠️ Service IA temporairement indisponible. Voici une suggestion aléatoire.', true)];
        }

        // If no good match found (below threshold), return random suggestion
        if (!$bestMatch || $bestScore < $threshold) {
            $this->logger->info("No good match found (best score: $bestScore). Returning random suggestion");
            $randomItem = $foundItems[array_rand(iterator_to_array($foundItems))];
            return [$this->formatMatchResult($randomItem, 30, '⚠️ Aucune correspondance précise trouvée. Voici un objet trouvé récemment qui pourrait vous intéresser.', true)];
        }

        // Return best match
        $this->logger->info("Best match found with score: $bestScore");
        return [$this->formatMatchResult($bestMatch, $bestScore, 'Match trouvé par analyse IA', false)];
    }

    /**
     * Format item as match result
     */
    private function formatMatchResult($item, $score, $explanation, $isRandom = false)
    {
        $foundDesc = $item->value('dcterms:description');
        $foundTitle = $item->value('dcterms:title');
        $foundText = $foundDesc ? $foundDesc->value() : '';
        $foundTitleText = $foundTitle ? $foundTitle->value() : '';

        $contactInfo = $this->extractContactInfo($foundText);
        
        // Get AI-generated summary if available
        $dataLiteral = $item->value('o:data');
        $itemData = $dataLiteral ? json_decode($dataLiteral->value(), true) : [];
        
        return [
            'item_id' => $item->id(),
            'title' => $foundTitleText,
            'description' => $foundText,
            'ai_description' => $itemData['ai_description'] ?? null,
            'similarity_score' => $score,
            'explanation' => $explanation,
            'is_random_suggestion' => $isRandom,
            'contact_phone' => $contactInfo['phone'] ?? null,
            'finder_name' => $contactInfo['name'] ?? null,
            'location' => $contactInfo['location'] ?? null,
            'created' => $item->created()->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Compare single item with user's lost description using AI
     * Returns similarity score 0-100 or false on failure
     */
    private function compareItemWithAI($lostTitle, $lostDescription, $foundTitle, $foundDescription)
    {
        // Truncate descriptions for faster processing
        $lostDesc = substr($lostDescription, 0, 150);
        $foundDesc = substr($foundDescription, 0, 150);
        $foundTitleShort = substr($foundTitle, 0, 50);

        $prompt = "Are these the same object? Lost object: \"$lostDesc\". Found object: \"$foundTitleShort - $foundDesc\". ";
        $prompt .= "Answer with similarity score 0-100. 100=identical, 70-90=very similar, 40-60=somewhat similar, 0-30=different objects. ";
        $prompt .= "Reply ONLY the number: ";

        $response = $this->textAiService->queryAI($prompt);

        if (!$response) {
            return false;
        }

        // Extract first number from response
        if (preg_match('/(\d+)/', trim($response), $matches)) {
            $score = intval($matches[1]);
            return min(100, max(0, $score));
        }

        return 10; // Low score if can't parse
    }

    /**
     * Extract contact information from found object description
     */
    private function extractContactInfo($description)
    {
        $info = [];
        
        // Extract phone number
        if (preg_match('/Téléphone du trouveur\s*:\s*([^\n]+)/i', $description, $matches)) {
            $info['phone'] = trim($matches[1]);
        } elseif (preg_match('/(\+?[\d\s\-\.]{10,})/i', $description, $matches)) {
            $info['phone'] = trim($matches[1]);
        }
        
        // Extract finder name
        if (preg_match('/Trouvé par\s*:\s*([^\n]+)/i', $description, $matches)) {
            $info['name'] = trim($matches[1]);
        }
        
        // Extract location
        if (preg_match('/Lieu\s*:\s*([^\n]+)/i', $description, $matches)) {
            $info['location'] = trim($matches[1]);
        }
        
        return $info;
    }
}