#! /bin/bash

# Clear the index?

# Omit 1_tables.json somehow

find . -name "*.json" -print0 | xargs -0 -I file curl -v --data-binary "@file" -XPOST localhost:9200/_bulk/
