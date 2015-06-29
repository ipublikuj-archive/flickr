# Flickr

[![Build Status](https://img.shields.io/travis/iPublikuj/flickr.svg?style=flat-square)](https://travis-ci.org/iPublikuj/flickr)
[![Scrutinizer Code Quality](https://img.shields.io/scrutinizer/g/iPublikuj/flickr.svg?style=flat-square)](https://scrutinizer-ci.com/g/iPublikuj/flickr/?branch=master)
[![Latest Stable Version](https://img.shields.io/packagist/v/ipub/flickr.svg?style=flat-square)](https://packagist.org/packages/ipub/flickr)
[![Composer Downloads](https://img.shields.io/packagist/dt/ipub/flickr.svg?style=flat-square)](https://packagist.org/packages/ipub/flickr)

Flickr API client with authorization for [Nette Framework](http://nette.org/)

## Installation

The best way to install ipub/flickr is using  [Composer](http://getcomposer.org/):

```sh
$ composer require ipub/flickr
```

After that you have to register extension in config.neon.

```neon
extensions:
	flickr: IPub\Flickr\DI\FlickrExtension
```

> NOTE: Don't forget to register [OAuth extension](http://github.com/iPublikuj/oauth), because this extension is depended on it!

## Documentation

Learn how to authenticate the user using Flickr's oauth or call Flickr's api in [documentation](https://github.com/iPublikuj/flickr/blob/master/docs/en/index.md).

***
Homepage [http://www.ipublikuj.eu](http://www.ipublikuj.eu) and repository [http://github.com/iPublikuj/flickr](http://github.com/iPublikuj/flickr).