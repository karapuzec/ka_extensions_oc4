How To Install Ka Extensions
============================

Ka Extensions is installed as a standard OpenCart 4 extension. The archive is called

::

    ka_extensions.ocmod.zip

.. note::

    OpenCart 4 uses the archive name as the extension code and the directory name
    (``extension/ka_extensions/``). Do not rename the archive.

Requirements
------------

OpenCart 4.1.0.3 or newer 4.1.x version (``store_version_min`` and ``store_version_max`` in
``install.json``). Older OpenCart 4 versions (4.0.x) are not supported.

Installation Steps
------------------

1. Log in to the admin area under an administrator account and upload the archive on the
   *Extensions > Installer* page.
2. In the list of installed extensions on the same page click the **Install** button next to
   *Ka Extensions*.
3. Open *Extensions > Modifications* and enable the *Ka Extensions Ocmod* modification.
4. Reload the *Modifications* page two times.

On the first load the *Modifications* page adds a few lines to ``system/framework.php``
(a backup copy ``framework.php.<random>.bak`` is saved next to it). Since then OpenCart loads
the OCMOD-modified copy of ``framework.php`` that starts kamod (see :doc:`ocmod`).

Verification
------------

- The *Ka Extensions* item appears in the *Extensions* menu. If it does not, reload the page
  or restart the browser.
- An ambulance icon appears in the admin header. A red counter on the icon shows the number
  of kamod errors (``kamod.errors.log`` in the logs directory).
- The storefront works.

The *Ka Extensions* page is always available to administrators with the *modify* permission
on the *User Permissions* page. Other user groups can be given access there as well.

Updating
--------

OpenCart 4 does not accept an archive of an extension that is already installed, and it does not
uninstall Ka Extensions while modules based on it are installed. Uninstalling the modules is not a good
option either: their settings are deleted.

To update Ka Extensions, copy the files of the new archive over the ``extension/ka_extensions/``
directory of the store (for example, with FTP). Kamod rebuilds its cache automatically because
``extension/ka_extensions/install.json`` becomes newer than the cache. Note that copying files does not
update the *Ka Extensions Ocmod* modification, OpenCart keeps its XML in the database.

When no modules based on Ka Extensions are installed, you can also uninstall the old version on the
*Extensions > Installer* page, delete it from the list and install the new archive as described above.
