<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Support\Facades\Storage;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Descriptions multilingues
        $descriptions = [
            'en' => "Witness a historic moment with the opening ceremony of the Paris 2024 Olympic Games. Experience an exceptional evening where sport, culture, and emotion meet in a grand spectacle in the heart of the City of Light. This ticket grants you access to an unforgettable celebration blending artistic performances, the parade of nations, and the spectacular lighting of the Olympic flame. A unique experience to share with thousands of spectators from around the world.",
            'fr' => "Assistez à un moment historique avec la cérémonie d’ouverture des Jeux Olympiques de Paris 2024. Vivez une soirée exceptionnelle où le sport, la culture et l’émotion se rencontrent dans un spectacle grandiose au cœur de la Ville Lumière. Ce billet vous donne accès à une célébration inoubliable mêlant performances artistiques, parade des nations, et allumage spectaculaire de la flamme olympique. Une expérience unique à partager avec des milliers de spectateurs venus du monde entier.",
            'de' => "Erleben Sie einen historischen Moment mit der Eröffnungsfeier der Olympischen Spiele Paris 2024. Genießen Sie einen außergewöhnlichen Abend, bei dem Sport, Kultur und Emotion in einem grandiosen Spektakel im Herzen der Stadt der Lichter aufeinandertreffen. Dieses Ticket gewährt Ihnen Zugang zu einer unvergesslichen Feier mit künstlerischen Darbietungen, der Nationenparade und der spektakulären Entzündung der olympischen Flamme. Ein einzigartiges Erlebnis, das Sie mit Tausenden von Zuschauern aus aller Welt teilen können.",
        ];

        // 2. Date et lieu
        $date     = '2024-07-26';
        $location = 'Stade de France, Saint-Denis';

        // 3. Variantes
        $variants = [
            ['places' => 1, 'price' => 100.00, 'sale' => 0.00, 'stock' => 50, 'seed' => 1],
            ['places' => 2, 'price' => 200.00, 'sale' => 0.10, 'stock' => 25, 'seed' => 2],
            ['places' => 4, 'price' => 400.00, 'sale' => 0.20, 'stock' => 15, 'seed' => 3],
        ];

        // 4. Répertoire des images d’exemple
        $sampleDir = database_path('seeders/sample_images');

        // 5. Parcours des variantes
        foreach ($variants as $v) {
            // Création du produit principal (anglais par défaut pour name et details)
            $product = Product::create([
                'name'            => 'Official Opening Ceremony of the Olympic Games',
                'product_details' => [
                    'places'      => $v['places'],
                    'description' => $descriptions['en'],
                    'date'        => $date,
                    'time'        => '7:30 PM (recommended entry from 6:00 PM)',
                    'location'    => $location,
                    'category'    => 'Ceremonies',
                    'image'       => null, // on mettra à jour après copie
                ],
                'price'           => $v['price'],
                'sale'            => $v['sale'],
                'stock_quantity'  => $v['stock'],
            ]);

            // Création des traductions FR et DE, image à null pour l'instant
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
                    'image'       => null,
                ],
            ]);
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
                    'image'       => null,
                ],
            ]);

            // 6. Copier les 3 images pour cette variante, une par locale
            foreach (['en','fr','de'] as $locale) {
                $source = "{$sampleDir}/{$v['seed']}_{$locale}.jpg";
                if (file_exists($source)) {
                    // Générer un nom de fichier unique comme store('', 'images')
                    $filename = time() . '_' . uniqid() . '.' . pathinfo($source, PATHINFO_EXTENSION);
                    // Copier dans storage/app/images
                    Storage::disk('images')->put($filename, file_get_contents($source));

                    // Mettre à jour la traduction correspondante
                    $translation = ProductTranslation::where('product_id', $product->id)
                                      ->where('locale', $locale)
                                      ->first();
                    if ($translation) {
                        $details = $translation->product_details;
                        if (!is_array($details)) {
                            $details = [];
                        }
                        $details['image'] = $filename;
                        $translation->product_details = $details;
                        $translation->save();
                    } else {
                        // Si vous souhaitiez gérer l'image au niveau principal :
                        $details = $product->product_details;
                        if (!is_array($details)) {
                            $details = [];
                        }
                        $details['image'] = $filename;
                        $product->product_details = $details;
                        $product->save();
                    }
                } else {
                    $this->command->warn("Sample image not found: {$source}");
                }
            }
            // Petite pause pour éviter de générer le même timestamp si nécessaire
            // sleep(1); // optionnel si de multiples appels très rapides
        }
    }
}

