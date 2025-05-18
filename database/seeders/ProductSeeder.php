<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductTranslation;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // 1. The descriptions of the product in different languages
        $descriptions = [
            'en' => [
                "Witness a historic moment with the opening ceremony of the Paris 2024 Olympic Games.",
                "Experience an exceptional evening where sport, culture, and emotion meet in a grand spectacle in the heart of the City of Light. This ticket grants you access to an unforgettable celebration blending artistic performances, the parade of nations, and the spectacular lighting of the Olympic flame. A unique experience to share with thousands of spectators from around the world.",
            ],
            'fr' => [
                "Assistez à un moment historique avec la cérémonie d’ouverture des Jeux Olympiques de Paris 2024.",
                "Vivez une soirée exceptionnelle où le sport, la culture et l’émotion se rencontrent dans un spectacle grandiose au cœur de la Ville Lumière. Ce billet vous donne accès à une célébration inoubliable mêlant performances artistiques, parade des nations, et allumage spectaculaire de la flamme olympique. Une expérience unique à partager avec des milliers de spectateurs venus du monde entier.",
            ],
            'de' => [
                "Erleben Sie einen historischen Moment mit der Eröffnungsfeier der Olympischen Spiele Paris 2024.",
                "Genießen Sie einen außergewöhnlichen Abend, bei dem Sport, Kultur und Emotion in einem grandiosen Spektakel im Herzen der Stadt der Lichter aufeinandertreffen. Dieses Ticket gewährt Ihnen Zugang zu einer unvergesslichen Feier mit künstlerischen Darbietungen, der Nationenparade und der spektakulären Entzündung der olympischen Flamme. Ein einzigartiges Erlebnis, das Sie mit Tausenden von Zuschauern aus aller Welt teilen können.",
            ],
        ];

        // 2. Fix date and location
        $date     = '2024-07-26';
        $location = 'Stade de France, Saint-Denis';

        // 3. Tickets variants
        $variants = [
            ['places' => 1, 'price' => 100.00, 'sale' => 0.00, 'stock' => 50, 'seed' => 1],
            ['places' => 2, 'price' => 200.00, 'sale' => 0.10, 'stock' => 25, 'seed' => 2],
            ['places' => 4, 'price' => 400.00, 'sale' => 0.20, 'stock' => 15, 'seed' => 3],
        ];

        foreach ($variants as $v) {
            // 4. Create the product
            $product = Product::create([
                'name'            => 'Official Opening Ceremony of the Olympic Games',
                'product_details' => [
                    'places'      => $v['places'],
                    'description' => $descriptions['en'],
                    'date'        => $date,
                    'time'        => '7:30 PM (recommended entry from 6:00 PM)',
                    'location'    => $location,
                    'category'    => 'Ceremonies',
                    'image'       => "https://picsum.photos/seed/{$v['seed']}/600/400",
                ],
                'price'           => $v['price'],
                'sale'            => $v['sale'],
                'stock_quantity'  => $v['stock'],
            ]);

            // 5. FR translation
            ProductTranslation::create([
                'product_id'      => $product->id,
                'locale'          => 'fr',
                'name'            => "Cérémonie d’ouverture officielle des JO",
                'product_details' => [
                    'places'      => $v['places'],
                    'description' => $descriptions['fr'],
                    'date'        => $date,
                    'time'        => '19h30 (accès recommandé dès 18h00)',
                    'location'    => $location,
                    'category'    => 'Cérémonies',
                    'image'       => "https://picsum.photos/seed/{$v['seed']}/600/400",
                ],
            ]);

            // 6. DE Translation
            ProductTranslation::create([
                'product_id'      => $product->id,
                'locale'          => 'de',
                'name'            => "Offizielle Eröffnungsfeier der Olympischen Spiele",
                'product_details' => [
                    'places'      => $v['places'],
                    'description' => $descriptions['de'],
                    'date'        => $date,
                    'time'        => '19:30 Uhr (empfohlener Einlass ab 18:00 Uhr)',
                    'location'    => $location,
                    'category'    => 'Zeremonien',
                    'image'       => "https://picsum.photos/seed/{$v['seed']}/600/400",
                ],
            ]);
        }
    }
}
