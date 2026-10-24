Safe Mode
=========

Safe mode is a way to open the admin area of the store without kamod. It can help when the admin area
does not work because of a kamod class or template of a module.

In safe mode kamod is not started in the admin area: OpenCart uses its original classes and templates,
the kamod cache is ignored. OCMOD modifications still work. The storefront is not affected.

Modules based on Ka Extensions and the *Ka Extensions* page may not work in safe mode. Use it only to fix
the store: for example, to disable or uninstall a module on the *Extensions > Installer* page.

How to Turn On Safe Mode
------------------------

Add the ``route=ka_safe_mode`` parameter to the admin URL. If your admin URL is

::

   https://www.mystore.com/admin/index.php

open

::

   https://www.mystore.com/admin/index.php?route=ka_safe_mode

Ka Extensions sets the ``ka_safe_mode`` cookie, and safe mode is on for the next admin requests of this
browser. The page itself shows the login form or an error page (*Permission Denied!*, *Page Not Found*)
because there is no page for this route. This is expected: log in if needed and open the page you need.

.. note::

    In the current version safe mode does not require a code. A protection code, like in Ka Extensions
    for OpenCart 3, is planned.

How to Turn Off Safe Mode
-------------------------

The cookie is kept until the browser is closed. Close all windows of the browser and open it again,
or delete the ``ka_safe_mode`` cookie of the store in the browser settings.
