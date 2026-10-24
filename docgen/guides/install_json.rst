install.json Reference
======================

``install.json`` is the standard manifest of an OpenCart 4 extension, it is located at the root of the
extension archive (``extension/<module>/install.json``). Ka Extensions reads additional fields from it.
In OpenCart 4 it replaces the ``kamod.ini`` file of Ka Extensions for OpenCart 3.

Example
-------

.. code-block:: json

    {
      "name": "My Module",
      "version": "1.0.0.0",
      "author": "My Company",
      "link": "https://www.example.com/my-module",
      "documentation_link": "https://www.example.com/my-module/docs",
      "ka_type": "ka_extensions",
      "ka_extensions_version": "5.1.1.0",
      "store_version_min": "4.1.0.3",
      "store_version_max": "4.1.9.9"
    }

Standard Fields
---------------

These fields are used by OpenCart on the *Extensions > Installer* page.

``name``
    Name of the extension. Ka Extensions shows it on its page and on the settings page.
``version``
    Version of the extension. Ka Extensions shows it on its page and on the settings page.
``author``
    Author of the extension.
``link``
    Link to the extension page. Ka Extensions shows it as the *Information* link.

Ka Extensions Fields
--------------------

``ka_type``
    Required. Must be ``"ka_extensions"``. Only extensions with this value are listed on the
    *Extensions > Ka Extensions* page and can be installed there.
``ka_extensions_version``
    Required. Version of Ka Extensions the module is made for. The installer of the module
    (``ControllerInstaller``) allows the installation when the version of the installed Ka Extensions is
    from this version up to the same version with ``99`` in the last number. For ``"5.1.1.0"`` it is
    ``5.1.1.0`` - ``5.1.1.99``. With other versions the installation stops with a message.
``documentation_link``
    Optional. Link to the documentation of the module, shown as the *Documentation* link.
``edition``
    Optional. Edition of the module, it is sent to ka-station.com together with the license key.
``store_version_min``, ``store_version_max``
    Optional. OpenCart versions supported by the module, for information. They are not checked by
    OpenCart 4.1 or Ka Extensions.

The ``ka_extensions`` extension itself does not have ``ka_type``: it is not listed on its own page.
