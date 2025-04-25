<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        Product::create([
            'name' => "Cérémonie d’ouverture officielle des JO",
            'product_details' => [
                'places' => 1,
                'description' =>
                    [
                        "Assistez à un moment historique avec la cérémonie d’ouverture des Jeux Olympiques de Paris 2024.",
                        "Vivez une soirée exceptionnelle où le sport, la culture et l’émotion se rencontrent dans un spectacle grandiose au cœur de la Ville Lumière. Ce billet vous donne accès à une célébration inoubliable mêlant performances artistiques, parade des nations, et allumage spectaculaire de la flamme olympique. Une expérience unique à partager avec des milliers de spectateurs venus du monde entier.",
                    ],
                'date' => "2024-07-26",
                'time' => "19h30 (accès recommandé dès 18h00)",
                'location' => "Stade de France, Saint-Denis",
                'category' => "Cérémonies",
                'image' => "https://picsum.photos/seed/1/600/400",
            ],
            'price' => 100.00,
            'sale' => 0.00,
            'stock_quantity' => 50,
        ]);

        Product::create([
            'name' => "Cérémonie d’ouverture officielle des JO",
            'product_details' => [
                'places' => 2,
                'description' =>
                    [
                        "Assistez à un moment historique avec la cérémonie d’ouverture des Jeux Olympiques de Paris 2024.",
                        "Vivez une soirée exceptionnelle où le sport, la culture et l’émotion se rencontrent dans un spectacle grandiose au cœur de la Ville Lumière. Ce billet vous donne accès à une célébration inoubliable mêlant performances artistiques, parade des nations, et allumage spectaculaire de la flamme olympique. Une expérience unique à partager avec des milliers de spectateurs venus du monde entier.",
                    ],
                'date' => "2024-07-26",
                'time' => "19h30 (accès recommandé dès 18h00)",
                'location' => "Stade de France, Saint-Denis",
                'category' => "Cérémonies",
                'image' => "https://picsum.photos/seed/2/600/400",
            ],
            'price' => 200.00,
            'sale' => 0.10,
            'stock_quantity' => 25,
        ]);

        Product::create([
            'name' => "Cérémonie d’ouverture officielle des JO",
            'product_details' => [
                'places' => 4,
                'description' =>
                    [
                        "Assistez à un moment historique avec la cérémonie d’ouverture des Jeux Olympiques de Paris 2024.",
                        "Vivez une soirée exceptionnelle où le sport, la culture et l’émotion se rencontrent dans un spectacle grandiose au cœur de la Ville Lumière. Ce billet vous donne accès à une célébration inoubliable mêlant performances artistiques, parade des nations, et allumage spectaculaire de la flamme olympique. Une expérience unique à partager avec des milliers de spectateurs venus du monde entier.",
                    ],
                'date' => "2024-07-26",
                'time' => "19h30 (accès recommandé dès 18h00)",
                'location' => "Stade de France, Saint-Denis",
                'category' => "Cérémonies",
                'image' => "https://picsum.photos/seed/3/600/400",
            ],
            'price' => 400.00,
            'sale' => 0.20,
            'stock_quantity' => 15,
        ]);
    }
}
