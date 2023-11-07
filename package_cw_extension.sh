#!/usr/bin/env bash

# Check for syntax errors
find . -type f -name *.php | xargs -n 1 php -l && \
zip -r Magento_ClassWallet-beta2.11.zip ClassWallet
