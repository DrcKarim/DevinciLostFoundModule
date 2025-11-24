<?php
namespace DevinciLostFoundModule\Service;

class ImageAiService
{
    private $apiEndpoint = "http://localhost:5000/describe"; // backend Python ou Node

    public function describeImage($imageUrl)
    {
        $payload = json_encode(['imageUrl' => $imageUrl]);

        $ch = curl_init($this->apiEndpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($result, true);

        return $json['description'] ?? null;
    }
}
