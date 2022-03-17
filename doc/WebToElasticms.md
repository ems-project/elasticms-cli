# WebToElasticms

With this Symfony single command, you can update elasticms documents by tracking web resources.

Usage 
 - `php application.php https://my-elasticms.com /path/to/a/json/config/file.json`

If you are not using a Linux environment, we suggest you to use a PHP docker image. I.e. under Windows with Docker Desktop: 

`docker run -it -v %cd%:/opt/src -w /opt/src elasticms/base-php-dev:7.4`
`php -d memory_limit=-1 bin/console ems:admin:login https://my-elasticms.com` 
`php -d memory_limit=-1 bin/console ems:admin:migrate /opt/src/config.json --cache-folder=/opt/src/cache --rapports-folder=/opt/src`

The JSON config file list all web resources to synchronise for each document.

```json
{
  "documents": [
    {
      "resources": [
        {
          "url": "https://fqdn.com/fr/page",
          "locale": "fr",
          "type": "infopage"
        },
        {
          "url": "https://fqdn.com/nl/page",
          "locale": "nl",
          "type": "infopage"
        },
        {
          "resources": [
            {
              "url": "http://www.inami.fgov.be/fr/themes/grossesse-naissance/maternite/Pages/repos-maternite-salariees-chomeuses.aspx",
              "locale": "fr",
              "type": "link"
            }
          ],
          "type": "link",
          "defaultData": {
            "fr": {
              "url": "http://www.inami.fgov.be/fr/themes/grossesse-naissance/maternite/Pages/repos-maternite-salariees-chomeuses.aspx",
              "label": "Repos de maternit\u00e9 pour les salari\u00e9es (INAMI)"
            },
            "nl": {
              "url": "http://www.inami.fgov.be/nl/themas/zwangerschap-geboorte/moederschap/Paginas/moederschapsrust-werkneemsters-werklozen.aspx",
              "label": "Moederschapsrust voor werkneemsters (RIZIV)"
            },
            "de": {
              "url": "http://www.inami.fgov.be/nl/themas/zwangerschap-geboorte/moederschap/Paginas/moederschapsrust-werkneemsters-werklozen.aspx",
              "label": "Repos de maternit\u00e9 pour les salari\u00e9es (LIKIV)"
            }
          }
        }
      ]
    }
  ],
  "analyzers": [
    {
      "name": "infopage",
      "type": "html",
      "extractors": [
        {
          "selector": "div.field-name-body div.field-item",
          "property": "[%locale%][body]",
          "filters": [
            "internal-link",
            "style-cleaner",
            "class-cleaner",
            "tag-cleaner"
          ]
        },
        {
          "selector": "h1",
          "property": "[%locale%][title]",
          "filters": [
            "striptags"
          ]
        },
        {
          "selector": "#block-system-main > div > ul > li > a",
          "property": "[internal_links]",
          "filters": [
            "data-link:link"
          ],
          "attribute": "href",
          "strategy": "n"
        },
        {
          "selector": "#block-system-main > div > div.institutions > div > div > ul > li",
          "property": "[author]",
          "filters": [
            "data-link:institution"
          ],
          "attribute": null,
          "strategy": "n"
        }
      ]
    },
    {
      "name": "link",
      "type": "empty-extractor",
      "extractors": []
    }
  ],
  "validClasses": ["toc"],
  "linkToClean": ["/^\\/fr\\/glossaire/"],
  "urlsNotFound": [
    "\/fr\/page-not-found"
  ],
  "linksByUrl": {
    "\/": "ems:\/\/object:page:xaO1YHoBFgLgfwq-PbIl"
  },
  "documentsToClean": {
    "page": [
      "w9WS4X0BFgLgfwq-9hDd",
      "y9YG4X0BeD9wLAROUfIV"
    ]
  },
  "dataLinksByUrl": {
    "institution": {
      "https://www.mi-is.be/": "institution:8OCq1H4BFgLgfwq-rYNZ",
      "CAAMI - HZIV": "institution:EuCt1H4BFgLgfwq-dYSB",
      "FEDRIS": "institution:Yd81vH4BFgLgfwq-nlw3"
    },
    "link": {
      "https://www.socialsecurity.be/citizen/fr/static/infos/general/index.htm": "link:X2AZan8BEIZ5tnyYFMjp",
      "https://www.socialsecurity.be/citizen/nl/static/infos/general/index.htm": "link:X2AZan8BEIZ5tnyYFMjp"
    }
  },
  "cleanTags": [
    "h1",
    "img"
  ]
}
```

## Filters

### class-cleaner

This filter remove all html class but the ones defined in the top level `validClasses` attribute. 

### internal-link

This filter convert internal links. A link is considered as an internal link if the link is relative, absolute or share the host with at least one resource. Internal link are converted following the ordered rules :
 - Link with a path matching at least on regex defined in the top level `linkToClean` attribute.
 - Link where the path match one of the resource with be converted to an ems link to document containing the resource
 - Link to an asset that is not a text/html are converte to an ems link to the asset (and the asset is uplaoded)

### style-cleaner

This filter remove all style attribute. 


### striptags

This filter extract the text and remove all the rest

### tag-cleaner

The filter remove all tag html define in cleanTags (h1 are a value by default) .

### data-link => data-link:category

This filter convert a string to data link. Data link are converted following the ordered rules :
- string matching at least defined in `dataLinksByUrl` for a given category in filter `data-link:category`.
- string maybe a path and where the path match one of the resource with be converted to a data link to document containing the resource


## Types

### tempFields

Array of string used to remove field from the data in order to not sent them to elasticms. It may append that you used temporary fields in order to save extractor values and used those values in computers. 

### Computer

#### Expression

Those parameters are using the [Symfony expression syntax](https://symfony.com/doc/current/components/expression_language/syntax.html)

Functions available: 
 - `uuid()`: generate a unique identifier
 - `json_escape(str)`: JSON escape a string 
 - `date(format, timestamp)`: Format a date 
 - `strtotime(str)`: Convert a string into a date 

Variable available
 - `data` an instance of [ExpressionData](src/Client/WebToElasticms/Helper/ExpressionData.php)

