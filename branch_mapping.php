<?php

use Drupal\taxonomy\Entity\Term;

$products = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
  'type' => 'sqs_industry'
]);
/** @var \Drupal\node\Entity\Node $product */
foreach ($products as $product) {
  $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
    'name' => $product->getTitle(),
    'vid' => 'branches'
  ]);
  $term = reset($term);


  if (empty($term)) {
    $term = Term::create([
      'name' => $product->getTitle(),
      'vid' => 'branches',
      'language' => 'de',
    ]);
    $term->save();
  }

  if (!$term->hasTranslation('en') && $product->hasTranslation('en')) {
    $term->addTranslation('en', ['name' => $product->getTranslation('en')->getTitle()]);
    $term->save();
  }

  if (!$term->hasTranslation('fr') && $product->hasTranslation('fr')) {
    $term->addTranslation('fr', ['name' => $product->getTranslation('fr')->getTitle()]);
    $term->save();
  }
  if (!$term->hasTranslation('it') && $product->hasTranslation('it')) {
    $term->addTranslation('it', ['name' => $product->getTranslation('it')->getTitle()]);
    $term->save();
  }
}