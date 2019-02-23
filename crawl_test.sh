#!/usr/bin/env bash
step=3
outfile="crawl_test_log.txt"

for((i=0;i<9;i+=step)); do
    now=$(date)
    echo -e "Crawl at $now: " >> $outfile
    curl http://newscrawl.net/index/Crawl/test >> $outfile
    echo -e "\n" >> $outfile
    sleep $step
done
echo -e "-----------------------------------------------------------\n" >> $outfile
exit 0