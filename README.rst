TYPO3 extension debug_mysql_db
==============================


What is does
------------

    • It transforms SQL error messages into debug messages or any function of your choice. This helps you to get rid of the output of SQL error messagein the Frontend or Backend.
    • It calculates the time for the performance of a SQL query.
    • You can filter to see only buggy SQL queries.
    • You can filter to see only SQL queries which take a long time.


Users manual
------------

Just install the extension in the Extension Manager. Then all database queries made by the PHP code of TYPO3 or of all TYPO3 extensions which use any TYPO3 method to make the database calls, will be caught. They are checked for errors. Time measurements are done on them.
The result of the database queries can be seen directly on the TYPO3 site. But you can configure it to use any functions like sending via email or storing it into a file. An easier method is to use an additional debug extension as e.g. fh_debug with it. By using additional extensions you can get a better and more understandable output.

Problems
---------

* The debug output is not generated within some parts of the Install Tool.
    see   `TYPO3 Core issue no. 99434 <https://forge.typo3.org/issues/99434/>`_
    Solution: See file ServiceProvider.diff in the Patches subfolder.


Installtool Requirement
------------------------

The class override mechanism has been deactivated in the TYPO3 core for the install tool.

To reactivate it again you must overwrite the TYPO3 Core file
``sysext/install/Classes/ServiceProvider.php`` by the file
``debug_mysql_db/Patches/TYPO3/sysext/install/Classes/ServiceProvider.php``.
Or you apply the patch file ``debug_mysql_db/Patches/ServiceProvider.diff`` on the TYPO3 core.

Alternatively you can use "post-install-cmd" or "post-autoload-dump" and a file copy method in your composer.json.
See `Defining scripts <https://getcomposer.org/doc/articles/scripts.md#defining-scripts>`__ .


