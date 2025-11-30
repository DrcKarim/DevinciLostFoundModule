<?php
namespace DescriptionWithAI\Service;

class TextAiService
{
    private $ollamaUrl = "http://localhost:11434/api/generate";

    public function summarizeText($text)
    {
        $payload = json_encode([
            "model" => "llama3.2-vision",
            "prompt" => "Summarize clearly this lost object description: \"$text\"",
            "stream" => false
        ]);

        $ch = curl_init($this->ollamaUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Accept: application/json"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        curl_close($ch);

        if (!$result) {
            return null;
        }

        // The response might contain multiple JSON blocks if streaming accidentally enabled
        // So we extract the LAST valid JSON object
        $lines = explode("\n", trim($result));
        $last = trim(end($lines));

        $json = json_decode($last, true);

        return $json['response'] ?? null;
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