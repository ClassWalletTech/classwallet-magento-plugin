#!/usr/bin/env bash
VERSION=beta2.13
# Check for syntax errors
find . -type f -name *.php | xargs -n 1 php -l && \
zip -r Magento_ClassWallet-$VERSION.zip ClassWallet && \
md5sum Magento_ClassWallet-$VERSION.zip
