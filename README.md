BounceHandler
=============

BounceHandler a [MediaWiki][] extension which allows a wiki to handle bounce
emails efficiently, by:

* Adding a variable envelope return path (VERP) 'Return-Path' header when
  `UserMailer::send` is used to send an email message.
* Providing a `bouncehandler` API endpoint which can be directly fed bounce
  notifications from the site's Message Transfer Agent (MTA) via an HTTP POST
  request.


Installation
------------
See https://www.mediawiki.org/wiki/Extension:BounceHandler#Installation


Configuration
-------------
See https://www.mediawiki.org/wiki/Extension:BounceHandler#Configuration and
https://www.mediawiki.org/wiki/Extension:BounceHandler#Parameters


License
-------
BounceHandler is licensed under the [GPLv2.0+][].


[MediaWiki]: https://www.mediawiki.org/
[GPLv2.0+]: https://www.gnu.org/licenses/gpl-2.0.html
