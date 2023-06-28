#!/bin/sh

# These are tests (courtesy msgvsa) for some of the logic in
# tome-sync.sh and tome-run.sh. Obviously we're not testing the scripts
# themselves, but this could be a start at something.

TOME_PUSH_NEW_CONTENT=1
RETRY_SEMAPHORE_FILE=/tmp/retry-on-next-run
ES_HOME_HTML_FILE=/tmp/index.html
ES_HOME_HTML_FILE_SMALL=/tmp/small-index.html
ES_HOME_HTML_FILE_NORMAL=/tmp/normal-index.html
ES_HOME_HTML_FILE_ENGLISH=/tmp/english-index.html

do_the_test () {

	export RETRY_SEMAPHORE_EXISTS=0
	if [ -f $RETRY_SEMAPHORE_FILE ]; then
	  export RETRY_SEMAPHORE_EXISTS=1
	  rm -f $RETRY_SEMAPHORE_FILE
	fi

	if [ "$RETRY_SEMAPHORE_EXISTS" != "0" ] ; then
	  echo
	  echo "Retry Semaphore file existed at startup."
	  echo
	fi

	ES_HOME_HTML_BAD=0
	if [ -f $ES_HOME_HTML_FILE ]; then
	  ES_HOME_HTML_SIZE=$(stat -c%s "$ES_HOME_HTML_FILE")
	else
	  ES_HOME_HTML_SIZE=0
	  ES_HOME_HTML_BAD=1
	  echo "WARNING: *** ES index.html does not exist ***"
	fi

	# Too-small file is probably a redirect page
	if [ $ES_HOME_HTML_BAD != "1" ] && [ $ES_HOME_HTML_SIZE -lt 1000 ]; then
	  echo "WARNING: *** ES index.html is way too small ($ES_HOME_HTML_SIZE bytes) ***"
	  ES_HOME_HTML_BAD=1
	fi

	if [ "$ES_HOME_HTML_BAD" != "1" ]; then
		# Sometimes Tome generates an English mobile menu on the Spanish home page
		ES_HOME_CONTAINS_ENGLISH_MENU=$(grep -c 'About us' $ES_HOME_HTML_FILE)
		if [ "$ES_HOME_CONTAINS_ENGLISH_MENU" != "0"  ]; then
	  		echo "WARNING: *** ES index.html appears to contain English nav ***"
	  		ES_HOME_HTML_BAD=1
		fi
	fi

	if [ "$ES_HOME_HTML_BAD" == "1" ]; then
	  # Delete the known-bad file; it may be re-created correctly on the next run.
	  rm -f $ES_HOME_HTML_FILE
	  touch $RETRY_SEMAPHORE_FILE
	  echo "Creating a retry semaphore file"
	  TOME_PUSH_NEW_CONTENT=0
	else
	  TOME_PUSH_NEW_CONTENT=1
	fi

    echo ES_HOME_HTML_BAD: $ES_HOME_HTML_BAD
	echo TOME_PUSH_NEW_CONTENT: $TOME_PUSH_NEW_CONTENT
}


cat <<STEXT > $ES_HOME_HTML_FILE_SMALL
too small to be a real html file
STEXT

cat <<NTEXT > $ES_HOME_HTML_FILE_NORMAL
HereDoc uses the ‘–‘ symbol to suppress any tab space from each line of heredoc text. In the following example, tab space is added at the start of each line, and the ‘–‘ symbol is used before the starting delimiter. When the script executes, all tab spaces are omitted from the starting of each line, but it creates no effect on normal space. Create a bash file named heredoc2.bash with the following script to test the function of ‘–‘.
HereDoc uses the ‘–‘ symbol to suppress any tab space from each line of heredoc text. In the following example, tab space is added at the start of each line, and the ‘–‘ symbol is used before the starting delimiter. When the script executes, all tab spaces are omitted from the starting of each line, but it creates no effect on normal space. Create a bash file named heredoc2.bash with the following script to test the function of ‘–‘.
HereDoc uses the ‘–‘ symbol to suppress any tab space from each line of heredoc text. In the following example, tab space is added at the start of each line, and the ‘–‘ symbol is used before the starting delimiter. When the script executes, all tab spaces are omitted from the starting of each line, but it creates no effect on normal space. Create a bash file named heredoc2.bash with the following script to test the function of ‘–‘.
HereDoc uses the ‘–‘ symbol to suppress any tab space from each line of heredoc text. In the following example, tab space is added at the start of each line, and the ‘–‘ symbol is used before the starting delimiter. When the script executes, all tab spaces are omitted from the starting of each line, but it creates no effect on normal space. Create a bash file named heredoc2.bash with the following script to test the function of ‘–‘.
NTEXT

cat <<ETEXT > $ES_HOME_HTML_FILE_ENGLISH
About us the ‘–‘ symbol to suppress any tab space from each line of heredoc text. In the following example, tab space is added at the start of each line, and the ‘–‘ symbol is used before the starting delimiter. When the script executes, all tab spaces are omitted from the starting of each line, but it creates no effect on normal space. Create a bash file named heredoc2.bash with the following script to test the function of ‘–‘.
HereDoc uses the ‘–‘ symbol to suppress any tab space from each line of heredoc text. In the following example, tab space is added at the start of each line, and the ‘–‘ symbol is used before the starting delimiter. When the script executes, all tab spaces are omitted from the starting of each line, but it creates no effect on normal space. Create a bash file named heredoc2.bash with the following script to test the function of ‘–‘.
HereDoc uses the ‘–‘ symbol to suppress any tab space from each line of heredoc text. In the following example, tab space is added at the start of each line, and the ‘–‘ symbol is used before the starting delimiter. When the script executes, all tab spaces are omitted from the starting of each line, but it creates no effect on normal space. Create a bash file named heredoc2.bash with the following script to test the function of ‘–‘.
HereDoc uses the ‘–‘ symbol to suppress any tab space from each line of heredoc text. In the following example, tab space is added at the start of each line, and the ‘–‘ symbol is used before the starting delimiter. When the script executes, all tab spaces are omitted from the starting of each line, but it creates no effect on normal space. Create a bash file named heredoc2.bash with the following script to test the function of ‘–‘.
ETEXT

#ls -l $ES_HOME_HTML_FILE_SMALL
#ls -l $ES_HOME_HTML_FILE_ENGLISH
#ls -l $ES_HOME_HTML_FILE_NORMAL

echo "#1. Try html file not exist"
	rm -f $ES_HOME_HTML_FILE
	do_the_test
	echo
	echo
	echo

echo "#2. Try html file too small"
	rm -f $ES_HOME_HTML_FILE
	cp $ES_HOME_HTML_FILE_SMALL $ES_HOME_HTML_FILE
	do_the_test
	echo
	echo
	echo

echo "# 3. Try html file english"
	rm -f $ES_HOME_HTML_FILE
	cp $ES_HOME_HTML_FILE_ENGLISH $ES_HOME_HTML_FILE
	do_the_test
	echo
	echo
	echo

echo "# 4. Try html file normal"
	rm -f $ES_HOME_HTML_FILE
	cp $ES_HOME_HTML_FILE_NORMAL $ES_HOME_HTML_FILE
	do_the_test
	echo
	echo
	echo

rm -f $ES_HOME_HTML_FILE
rm -f $ES_HOME_HTML_FILE_ENGLISH
rm -f $ES_HOME_HTML_FILE_NORMAL
rm -f $ES_HOME_HTML_FILE_SMALL
rm -f $RETRY_SEMAPHORE_FILE

