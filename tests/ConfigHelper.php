<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests;

trait ConfigHelper
{
    public function getShopkey(): string
    {
        return '80AB18D4BE2654E78244106AD315DC2C';
    }

    public function getConfig(?bool $assoc = true)
    {
        $config = '{
          "blocks": {
            "cat": "Kategorie",
            "suggest": "Suchvorschl\u00e4ge",
            "vendor": "Hersteller",
            "product": "Produkte",
            "promotion": "Empfehlungen",
            "landingpage": "Inhalte",
            "allResult": "Alle Ergebnisse anzeigen f\u00fcr:",
            "ordernumber": "Artikelnummer:"
          },
          "autocompleteType": "result_v3",
          "autocompleteMobileMaxWidth": "480px",
          "mobileSmartSuggest": {
            "config": {
              "enabled": true,
              "voiceSearchEnabled": true,
              "primaryColor": "#444444",
              "submitColor": "#fff"
            },
            "text": {
              "searchPlaceholder": "Suchen",
              "noResults": "Keine genauen Ergebnisse",
              "showRelatedResults": "\u00c4hnliche Ergebnisse anzeigen",
              "voiceSearchError": "Spracherkennung fehlgeschlagen. Bitte pr\u00fcfen Sie, ob der Browser berechtigt ist, auf das Mikrofon zuzugreifen."
            }
          },
          "searchFieldSelector": "input[name=\"q\"][type=\"text\"], input[name=\"q\"][type=\"search\"]",
          "searchFieldName": "q",
          "autocompleteProxy": "\/\/service.findologic.com\/ps\/example.com\/autocomplete.php",
          "useAutocompleteProxy": false,
          "currency": "\u20ac",
          "shopSystem": "magento",
          "cssFile": [
            "\/\/cdn.findologic.com\/login.symfony\/web\/autocomplete\/9B237A6DDF2ECF2A632CE652477F386D\/fl_smart_suggest.css"
          ],
          "configUrl": "\/\/cdn.findologic.com\/login.symfony\/web\/autocomplete\/9B237A6DDF2ECF2A632CE652477F386D\/config.json",
          "frontendConfig": {
            "useJQueryUiCss": false,
            "frontendType": "HTML_3.1"
          },
          "showLogo": true,
          "debugMode": 1,
          "defaultSearchFieldSelectors": [
            "input[name=\"Params[SearchParam]\"][type=\"text\"]",
            "input[name=\"searchparam\"][type=\"text\"]",
            "input[name=\"keywords\"][type=\"text\"]",
            "input[name=\"q\"][type=\"text\"]",
            "input[name=\"sSearch\"][type=\"text\"]",
            "input[name=\"search_input\"][type=\"text\"]",
            "input[name=\"SearchStr\"][type=\"text\"]",
            "input[name=\"query\"][type=\"text\"]",
            "input[name=\"Params[SearchParam]\"][type=\"search\"]",
            "input[name=\"searchparam\"][type=\"search\"]",
            "input[name=\"keywords\"][type=\"search\"]",
            "input[name=\"q\"][type=\"search\"]",
            "input[name=\"sSearch\"][type=\"search\"]",
            "input[name=\"search_input\"][type=\"search\"]",
            "input[name=\"SearchStr\"][type=\"search\"]",
            "input[name=\"query\"][type=\"search\"]",
            "#flAutocompleteInputText"
          ],
          "shopkey": "74B87337454200D4D33F80C4663DC5E5",
          "shopUrl": "www.example.com",
          "language": "de",
          "hashedShopkey": "9B237A6DDF2ECF2A632CE652477F386D",
          "useTwoColumnLayout": true,
          "status": "active",
          "isStagingShop": false,
          "directIntegration": {
            "enabled": true,
            "frontend": "\/\/service.findologic.com\/ps\/example.com\/\/",
            "navigationType": 0,
            "callbacks": {
              "init": "function directIntegrationInitCallback(response) {}",
              "preSearch": "function directIntegrationPreSearchCallback(queryString) {}",
              "searchSuccess": "function directIntegrationSearchSuccessCallback(response) {}",
              "error": "function directIntegrationErrorCallback(response) {}",
              "navigationSuccess": "function directIntegrationNavigationSuccessCallback(response, resultsAvailable) {}",
              "preNavigation": "function directIntegrationPreNavigationCallback(queryString) {}"
            }
          },
          "guidedShoppingModule": "wizard",
          "cdnBaseUrl": "\/\/cdn.findologic.com\/login.symfony\/web",
          "cacheBuster": "1569828935",
          "trackMerchandisingFeatures": true,
          "trackSmartSuggest": true,
          "trackFilters": true,
          "trackResultsWithFindologic": false,
          "trackResultsWithAnalytics": true,
          "tracking": {
            "analytics": []
          },
          "useErrorTracking": true,
          "errorTracker": "raven",
          "ravenConfig": {
            "serviceUrl": "https:\/\/728c4d85526a066f469323b3f6fb3693@sentry.io\/78419789",
            "scriptWhitelist": "cdn.findologic.com"
          }
        }';

        return json_decode($config, $assoc);
    }
}
