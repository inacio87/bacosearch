<?php
// core/repositories/ModelRepository.php

class ModelRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Fetch featured/active models with one primary photo
     * @param int $limit
     * @return array<int,array<string,mixed>>
     */
    public function getFeaturedModels(int $limit = 24): array
    {
        // Adjust ORDER BY for your real business rules (featured flag, recency, etc.)
        $sql = "SELECT m.id, m.slug, m.display_name, m.price, m.price_period, 
                       m.city_id, m.neighborhood_id, m.phone,
                       p.url AS photo_url,
                       ne.name AS neighborhood_name
                FROM models m
                LEFT JOIN model_photos p ON p.model_id = m.id AND p.is_primary = 1
                LEFT JOIN neighborhoods_en ne ON ne.id = m.neighborhood_id
                WHERE m.is_active = 1
                ORDER BY m.updated_at DESC
                LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(function(array $r){
            return [
                'id' => $r['id'],
                'slug' => $r['slug'],
                'name' => $r['display_name'],
                'neighborhood' => $r['neighborhood_name'] ?: '',
                'price' => $r['price'],
                'period' => $r['price_period'],
                'whatsapp' => $r['phone'],
                'thumb' => $r['photo_url'] ?: '/placeholder-thumb.jpg',
                'full'  => $r['photo_url'] ?: '/placeholder-full.jpg',
            ];
        }, $rows);
    }

    /**
     * Gather filter lists (cities, neighborhoods, categories)
     * @return array<string,array<int,array<string,string>>>
     */
    public function getFilters(): array
    {
        $cities = $this->fetchSimple('SELECT id, slug, name FROM cities ORDER BY name ASC');
        $neighborhoods = $this->fetchSimple('SELECT id, slug, name FROM neighborhoods_en ORDER BY name ASC');
        $categories = $this->fetchSimple('SELECT id, slug, name FROM categories_en ORDER BY name ASC');
        return [
            'cities' => $cities,
            'neighborhoods' => $neighborhoods,
            'categories' => $categories,
        ];
    }

    /**
     * @param string $sql
     * @return array<int,array<string,string>>
     */
    private function fetchSimple(string $sql): array
    {
        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll();
        return array_map(function(array $r){
            return [
                'id' => (string)$r['id'],
                'slug' => (string)$r['slug'],
                'name' => (string)$r['name'],
            ];
        }, $rows);
    }
}
