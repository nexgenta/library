PHP_5 ?= php
top_builddir := $(shell pwd)
top_srcdir := $(shell pwd)

all: pull doxygen

pull:
	git submodule foreach git pull origin master

doxygen:
	git submodule foreach test -r Doxyfile '&&' doxygen

scan:
	test -d docbook || mkdir docbook
	git submodule foreach test -d doxydocs '&&' $(PHP_5) -f $(top_srcdir)/scandoxy.php doxydocs $(top_builddir)/docbook

process: scan
	$(PHP_5) -f processdb.php docbook .

clean:
	rm -rf docbook libradiodns
