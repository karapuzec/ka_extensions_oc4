Sending Emails
==============

``\Opencart\Extension\KaExtensions\System\Mail`` sends emails rendered from twig templates with the
store information (name, email, logo, URL) in the language of the recipient.

.. warning::

    The class has known issues in Ka Extensions 5.1.1.0, see `Known Issues`_ below.

Basic Usage
-----------

.. code-block:: php

    use \Opencart\Extension\KaExtensions\System\Mail;

    $mail = new Mail($language_id, $store_id);

    $mail->data['order_id'] = $order_id;

    $mail->send('', $email, 'Order is shipped', 'extension/my_module/mail/order_shipped');

Constructor parameters:

- ``$language_id`` - id of the language the email is rendered in. The store language is used when it is
  empty;
- ``$store_id`` - store whose name, email, logo and URL are used (default ``0``).

``send($from, $to, $subject, $tpl, $extra = [])``:

- ``$from`` - sender email, the store email when empty;
- ``$to`` - recipient email;
- ``$subject`` - subject of the email. It is also passed to the templates as ``subject``, translated
  if it is a language variable;
- ``$tpl`` - template route without the extension, like in ``$this->load->view()``.
  ``<tpl>.twig`` is the HTML part, ``<tpl>_txt.twig`` is the text part. For
  ``extension/my_module/mail/order_shipped`` called in the storefront these are
  ``extension/my_module/catalog/view/template/mail/order_shipped.twig`` and ``order_shipped_txt.twig``;
- ``$extra`` - options: ``reply_to`` sets the *Reply-To* header.

Data available in templates: ``store_name``, ``sender``, ``store_url`` (the customer login page of
the store), ``subject``, ``logo`` (URL of the store logo) and everything added to ``$mail->data``.

Templates per Language
----------------------

A template with the language code suffix is used first if it exists, e.g. ``order_shipped_de-de.twig``,
otherwise ``order_shipped.twig``.

Attachments
-----------

.. code-block:: php

    $mail->addAttachment(DIR_DOWNLOAD . $filename, 'invoice.pdf');

The second parameter is the file name shown in the email (optional). Ka Extensions adds named attachments
to the standard OpenCart mail class too: ``$mail->mail->addNamedAttachment($filename, $name)``.

Logging
-------

When the ``KA_EXTENSIONS_LOG_EMAILS`` constant is defined (see :doc:`useful_constants`), every email sent
through OpenCart, not only through this class, is written to ``ka_mail_log.log`` in the logs directory
with the result: success or the error message.

Without this constant, errors of sending are suppressed, so a broken mail server does not break the page.

Known Issues
------------

In Ka Extensions 5.1.1.0:

- the email is always sent with the PHP ``mail()`` function, the mail engine and SMTP settings of the store
  are not used;
- both templates, ``<tpl>.twig`` and ``<tpl>_txt.twig``, must exist, otherwise a fatal error occurs;
- a language code (``'en-gb'``) cannot be passed to the constructor, use the language id;
- ``sendContent()`` (an email wrapped into common header and footer templates) does not work: the common
  templates are not included in the library.
