<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/** Licensed remote menu photos; maintain URLs and credits together. */
final class MenuImages extends BaseConfig
{
    public array $products = [
        'chicken-adobo-rice' => ['url' => 'https://images.pexels.com/photos/6896058/pexels-photo-6896058.jpeg?auto=compress&cs=tinysrgb&w=800&h=600&fit=crop', 'alt' => 'Filipino adobo with egg served beside steamed rice', 'source_page' => 'https://www.pexels.com/photo/mouthwatering-pork-adobo-with-eggs-beside-the-bowl-of-rice-6896058/', 'author' => 'Eiliv Aceron', 'license' => 'Pexels License'],
        'pork-sisig-rice' => ['url' => 'https://images.pexels.com/photos/6896281/pexels-photo-6896281.jpeg?auto=compress&cs=tinysrgb&w=800&h=600&fit=crop', 'alt' => 'Filipino pork sisig with crispy garlic and chives in a cast-iron pan', 'source_page' => 'https://www.pexels.com/photo/cooked-meat-on-black-cast-iron-cookware-6896281/', 'author' => 'Eiliv Aceron', 'license' => 'Pexels License'],
        'fried-chicken-meal' => ['url' => 'https://images.unsplash.com/photo-1603496987674-79600a000f55?fm=webp&q=75&w=800&h=600&fit=crop', 'alt' => 'Fried chicken served on a plate of rice', 'source_page' => 'https://unsplash.com/photos/fried-chicken-on-yellow-rice-77ItGP98s6g', 'author' => 'Dr Muhammad Amer', 'license' => 'Unsplash License'],
        'cheese-burger' => ['url' => 'https://images.unsplash.com/photo-1626111740066-e595bce778f0?fm=webp&q=75&w=800&h=600&fit=crop', 'alt' => 'Cheeseburger with lettuce and tomato', 'source_page' => 'https://unsplash.com/photos/burger-with-lettuce-and-tomato-_uju2LIPCSg', 'author' => 'Amanda Lim', 'license' => 'Unsplash License'],
        'crispy-fries' => ['url' => 'https://images.unsplash.com/photo-1526230427044-d092040d48dc?fm=webp&q=75&w=800&h=600&fit=crop', 'alt' => 'Golden crispy French fries', 'source_page' => 'https://unsplash.com/photos/potato-fries-YOW3de4wEDk', 'author' => 'Gilly', 'license' => 'Unsplash License'],
        'iced-spanish-latte' => ['url' => 'https://images.unsplash.com/photo-1676506739319-70bff65bfc48?fm=webp&q=75&w=800&h=600&fit=crop', 'alt' => 'Milk being poured into a glass of iced coffee', 'source_page' => 'https://unsplash.com/photos/a-person-pouring-coffee-into-a-glass-filled-with-ice-OSLnJG62isk', 'author' => 'Sarah Swainson', 'license' => 'Unsplash License'],
        'hot-americano' => ['url' => 'https://images.unsplash.com/photo-1551490434-bf8bc9eab4a9?fm=webp&q=75&w=800&h=600&fit=crop', 'alt' => 'Hot black coffee in a ceramic cup and saucer', 'source_page' => 'https://unsplash.com/photos/blue-ceramic-coffee-cup-on-blue-saucer-DvaoJEksloQ', 'author' => 'Daniel Norris', 'license' => 'Unsplash License'],
        'calamansi-juice' => ['url' => 'https://images.unsplash.com/photo-1579109788443-68073c1fd623?fm=webp&q=75&w=800&h=600&fit=crop', 'alt' => 'Chilled citrus juice with ice and fresh citrus slices', 'source_page' => 'https://unsplash.com/photos/leaf-and-fruit-slices-and-beverage-and-iced-filled-drinking-glass-_0zCa-pRUE8', 'author' => 'zahra owji', 'license' => 'Unsplash License'],
    ];

    public array $categories = [
        'Rice Meals' => ['url' => 'https://images.pexels.com/photos/33030612/pexels-photo-33030612.jpeg?auto=compress&cs=tinysrgb&w=800&h=600&fit=crop', 'alt' => 'Rice meal with chicken and salad', 'source_page' => 'https://www.pexels.com/photo/delicious-plate-of-rice-and-chicken-meal-33030612/', 'author' => 'nobleseed', 'license' => 'Pexels License'],
        'Snacks' => ['url' => 'https://images.unsplash.com/photo-1561758033-d89a9ad46330?fm=webp&q=75&w=800&h=600&fit=crop', 'alt' => 'Burger with fries', 'source_page' => 'https://unsplash.com/photos/burger-with-fries-8l8Yl2ruUsg', 'author' => 'Jonathan Borba', 'license' => 'Unsplash License'],
        'Coffee' => ['url' => 'https://images.unsplash.com/photo-1640735099606-8181a8c8f5a7?fm=webp&q=75&w=800&h=600&fit=crop', 'alt' => 'Cup of coffee on a wooden table', 'source_page' => 'https://unsplash.com/photos/a-cup-of-coffee-sitting-on-top-of-a-wooden-table-G0DKq4yz5AA', 'author' => 'Karl Hörnfeldt', 'license' => 'Unsplash License'],
        'Cold Drinks' => ['url' => 'https://images.unsplash.com/photo-1608981618938-d8a334ae9e93?fm=webp&q=75&w=800&h=600&fit=crop', 'alt' => 'Chilled lime drink with ice and mint', 'source_page' => 'https://unsplash.com/photos/clear-drinking-glass-with-ice-and-lime-ohoOQxgvB0w', 'author' => 'Johanne Pold Jacobsen', 'license' => 'Unsplash License'],
    ];
}
