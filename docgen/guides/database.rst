Database: Db, QB and Tables
===========================

Ka Extensions has simple helpers for database queries. All of them work with the standard
OpenCart database connection and add ``DB_PREFIX`` to table names themselves.

.. note::

    The ORM classes of Ka Extensions for OpenCart 3 (``ADBTable``, ``ModelRecordset``) are not
    available in Ka Extensions for OpenCart 4.

Db
--

``\Opencart\Extension\KaExtensions\System\Db`` builds ``INSERT`` and ``UPDATE`` queries from arrays.
Controllers and models based on Ka Extensions have its object in ``$this->kadb``:

.. code-block:: php

    // INSERT INTO oc_my_module_label SET `name` = 'New', `product_id` = '42'
    $label_id = $this->kadb->insert('my_module_label', [
        'name'       => 'New',
        'product_id' => 42,
    ]);

    // UPDATE oc_my_module_label SET `name` = 'Sale' WHERE label_id = '5'
    $this->kadb->update('my_module_label', ['name' => 'Sale'], "label_id = '5'");

    // INSERT ... ON DUPLICATE KEY UPDATE ...
    $this->kadb->insertOrUpdate('my_module_label', $data);

- Values are escaped, ``null`` becomes ``NULL``.
- An element with a numeric key is added as a raw SQL expression, e.g. ``['date_added = NOW()']``.
- ``insert()`` returns the id of the new record. ``insert($table, $data, true)`` runs ``REPLACE``.
- ``update()`` without a condition updates all rows of the table.
- ``queryHash($sql, $key)`` returns rows indexed by the value of the ``$key`` column.

QB (Query Builder)
------------------

``\Opencart\Extension\KaExtensions\System\QB`` collects parts of a ``SELECT`` query. A method that
returns a ``QB`` object can be extended by other modules (see :doc:`code_design_guidlines`):

.. code-block:: php

    use \Opencart\Extension\KaExtensions\System\QB;

    protected function getLabelsQB(array $data): QB {
        $qb = new QB();
        $qb->select('l.*, pd.name AS product_name', 'my_module_label', 'l');
        $qb->leftJoin('product_description', 'pd',
            "pd.product_id = l.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "'");
        $qb->where('l.status', 1);
        $qb->orderBy('l.sort_order');
        $qb->limit(0, 20);
        return $qb;
    }

    public function getLabels(array $data): array {
        return $this->getLabelsQB($data)->query()->rows;
    }

Methods:

``select($what, $table = '', $alias = '')``
    Adds an expression to ``SELECT`` and optionally a table to ``FROM``.
``from($table, $alias = '')``
    Adds a table to ``FROM``.
``innerJoin($table, $alias, $condition)``, ``leftJoin($table, $alias, $condition)``
    Add joins.
``where($condition)``, ``where($field, $value)``
    Add conditions joined with ``AND``. With two parameters the value is escaped and compared with ``=``.
    An array of conditions is joined with ``OR``.
``orderBy($expression)``, ``groupBy($expression)``
    Set ``ORDER BY`` and ``GROUP BY``.
``limit($start, $limit)``
    Sets ``LIMIT``.
``getSql()``, ``query()``
    Return the SQL text or run the query (the result of ``$this->db->query()``).

.. warning::

    In Ka Extensions 5.1.1.0 several ``orderBy()`` or ``groupBy()`` calls are joined without a comma.
    Pass all expressions in one call: ``$qb->orderBy('l.sort_order, pd.name')``.

Tables of the Module
--------------------

The installer of the module (``ControllerInstaller`` and ``ControllerSettings``) creates and updates
database tables listed in ``$this->tables``. Set it in ``onLoad()``:

.. code-block:: php

    protected function onLoad() {
        $this->tables = array(
            'my_module_label' => array(
                'query' => "CREATE TABLE `" . DB_PREFIX . "my_module_label` (
                    `label_id` int(11) NOT NULL AUTO_INCREMENT,
                    `product_id` int(11) NOT NULL,
                    `name` varchar(255) NOT NULL DEFAULT '',
                    PRIMARY KEY (`label_id`)
                ) DEFAULT CHARSET=utf8mb4",
                'fields' => array(
                    'name' => array(
                        'query' => "ALTER TABLE `" . DB_PREFIX . "my_module_label`
                            ADD `name` varchar(255) NOT NULL DEFAULT ''",
                    ),
                ),
                'indexes' => array(
                    'product_id' => array(
                        'query' => "ALTER TABLE `" . DB_PREFIX . "my_module_label` ADD INDEX (`product_id`)",
                    ),
                ),
            ),
        );
        return parent::onLoad();
    }

On installation:

- a missing table is created with ``query``;
- for an existing table, missing fields and indexes (by name) are added with their ``query``;
- types of existing fields are not checked.

Tables are not deleted on uninstallation.

Tables::getTable()
------------------

``\Opencart\Extension\KaExtensions\System\Tables::getTable($route)`` returns a model by its route
and keeps one object per route, for example
``Tables::getTable('extension/my_module/label')``. It uses ``$this->load->kamodel()``.

``$this->load->kamodel($route)`` is added to the OpenCart loader by Ka Extensions. It creates the model
object directly, without the OpenCart proxy object, so model events are not triggered. The object is stored
in the registry under the same name as with ``$this->load->model()`` (``model_extension_my_module_label``);
if the model is already there, the stored object is returned.
