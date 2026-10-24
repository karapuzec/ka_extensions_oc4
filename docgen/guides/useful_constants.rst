Useful Constants
================

Ka Extensions checks several PHP constants. Define them in ``config.php`` of the store root
and in ``admin/config.php``, for example:

.. code-block:: php

    define('KAMOD_DEBUG', 1);

The constants are checked with ``defined()``, so any value turns the feature on. Remove the line
to turn it off.


KAMOD_DEBUG
-----------

For development only:

- the kamod cache is rebuilt on every request, so changes in ``kamod`` directories of modules
  are applied at once;
- a locked kamod cache (another request is rebuilding it) does not stop the page;
- when kamod fails to start, the request stops with the error message (``KAMOD DEBUG: ...``);
- twig rendering errors are shown on the page and written to the error log. Without
  ``KAMOD_DEBUG`` a template with an error is rendered as an empty string.

The OCMOD modifications are not refreshed by this constant.


KAMOD_DEBUG_SUPPRESS_ERRORS
---------------------------

Used together with ``KAMOD_DEBUG``: twig rendering errors are not shown and not logged, a template
with an error is rendered as an empty string. The kamod cache is still rebuilt on every request.


KA_EXTENSIONS_LOG_EMAILS
------------------------

Logs every email sent through OpenCart (``\Opencart\System\Library\Mail``): the sender, recipients,
subject and the result are written to ``ka_mail_log.log`` in the logs directory. The value must not be
empty, e.g. ``define('KA_EXTENSIONS_LOG_EMAILS', 1);``. See :doc:`mail`.


KA_STORE_URL
------------

Changes the URL of the ka-station.com server used for license registration and the information shown on
the Ka Extensions page. The default value is ``https://www.ka-station.com/``. It is intended for
development and testing of the server side only.
