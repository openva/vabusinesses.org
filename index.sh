#! /bin/bash

cd /vol/www/business.openva.com/crump/output/

# Delete the index
curl -XDELETE 'http://localhost:9200/business/'

# Create the index anew.
curl -XPUT 'http://localhost:9200/business/'

# Re-specify table structure
cd elasticsearch_maps
curl -v --data-binary "@1.json" -XPUT localhost:9200/business/1/_mapping
curl -v --data-binary "@2.json" -XPUT localhost:9200/business/2/_mapping
curl -v --data-binary "@3.json" -XPUT localhost:9200/business/3/_mapping
curl -v --data-binary "@4.json" -XPUT localhost:9200/business/4/_mapping
curl -v --data-binary "@5.json" -XPUT localhost:9200/business/5/_mapping
curl -v --data-binary "@6.json" -XPUT localhost:9200/business/6/_mapping
curl -v --data-binary "@7.json" -XPUT localhost:9200/business/7/_mapping
curl -v --data-binary "@8.json" -XPUT localhost:9200/business/8/_mapping
curl -v --data-binary "@9.json" -XPUT localhost:9200/business/9/_mapping
cd ..

## TODO: Only index Elasticsearch files. Right now, it's trying to index *everything*.
find . -maxdepth 1 -name "[!1]_*.json" -print0 | xargs -0 -I file curl -v --data-binary "@file" -XPOST localhost:9200/_bulk/

