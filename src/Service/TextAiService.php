<?php
namespace DescriptionWithAI\Service;

class TextAiService
{
    private $ollamaUrl = "http://localhost:11434/api/generate";

    public function summarizeText($text)
    {
        // Sanitize and prepare the text
        $cleanText = trim($text);
        if (empty($cleanText)) {
            return null;
        }
        
        // Clean up the text - remove problematic characters
        $cleanText = mb_convert_encoding($cleanText, 'UTF-8', 'UTF-8');
        $cleanText = preg_replace('/[^\p{L}\p{N}\s\-\.\,\:\;\!\?]/u', ' ', $cleanText);
        $cleanText = preg_replace('/\s+/', ' ', $cleanText);
        
        if (strlen($cleanText) > 800) {
            $cleanText = substr($cleanText, 0, 800) . "...";
        }
        
        // Simple prompt without complex formatting
        $prompt = "Describe this object briefly: $cleanText";
        
        $payload = json_encode([
            "model" => "llama2",
            "prompt" => $prompt,
            "stream" => false,
            "options" => [
                "temperature" => 0.1,
                "num_predict" => 100
            ]
        ], JSON_UNESCAPED_UNICODE);

        return $this->makeOllamaRequest($payload);
    }

    /**
     * Generic AI query method for various tasks
     * Optimized for faster responses with shorter context
     */
    public function queryAI($prompt)
    {
        $payload = json_encode([
            "model" => "llama2",
            "prompt" => $prompt,
            "stream" => false,
            "options" => [
                "temperature" => 0.1,      // Lower = more consistent
                "top_k" => 10,             // Limit token choices for speed
                "top_p" => 0.5,            // Nucleus sampling for speed
                "num_predict" => 50,       // Very short response
                "num_ctx" => 512           // Smaller context window for speed
            ]
        ]);

        return $this->makeOllamaRequest($payload);
    }

    /**
     * Make a request to Ollama API
     */
    private function makeOllamaRequest($payload)
    {
        try {
            $ch = curl_init($this->ollamaUrl);
            
            if (!$ch) {
                error_log("Failed to initialize cURL");
                return null;
            }
            
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Accept: application/json"
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 1 minute timeout - fail faster
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // 5 seconds to connect
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // In case of SSL issues

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                error_log("cURL error: " . $curlError);
                return null;
            }

            if (!$result) {
                error_log("Empty response from Ollama API");
                return null;
            }

            if ($httpCode !== 200) {
                error_log("Ollama API returned HTTP $httpCode: " . substr($result, 0, 500));
                return null;
            }

            // The response might contain multiple JSON blocks if streaming accidentally enabled
            // So we extract the LAST valid JSON object
            $lines = explode("\n", trim($result));
            $last = trim(end($lines));

            $json = json_decode($last, true);

            if (!$json) {
                error_log("Failed to decode Ollama response: " . substr($last, 0, 200));
                return null;
            }

            return $json['response'] ?? null;
            
        } catch (Exception $e) {
            error_log("Exception in makeOllamaRequest: " . $e->getMessage());
            return null;
        }
    }
}

/*
<?php
namespace DescriptionWithAI\Service;

class TextAiService
{
    private $ollamaUrl = "http://localhost:11434/api/generate";

    public function summarizeText($text)
    {
        $payload = json_encode([
            "model" => "llama3.1:8b",
            "prompt" => "Summarize this lost object description: \"$text\"",
            "stream" => false
        ]);

        $ch = curl_init($this->ollamaUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        curl_close($ch);

        if (!$result) return null;

        $json = json_decode($result, true);

        return $json['response'] ?? null;
    }
}


*/