#!/opt/local/bin/perl -w
# wa2bb.pl
#
# a script to convert downloads from WebAssign into files uploadable to Blackboard.
#
# TODO: 
# * find a smart way to remove instructors 
# 

use Text::CSV;
use Data::Dumper;

sub main{
  my $csv = Text::CSV->new({binary=>1,sep_char=>"\t"});
  my $line='';
  my ($status,@columns,@fields,$err);
  $/ = "\r";
  while (<>) {
    next if ($. <  9);

    if ($csv->parse($_)) {
      if ($. == 9) {
	@columns = $csv->fields();
	print qq!"Last Name"	"First Name "	"Username"	"Student ID"	"Last Access"	"Availability"	"WebAssign"\n!;
	next;
      }
      @columns = $csv->fields();
      ($lname,$fname) = split /, /, $columns[0];
      ($netid) = split /@/, $columns[1];
      printf qq!"%s"\t"%s"\t"%s"\t\t"Yes"\t"%d"\n!, $lname, $fname, $netid, $columns[3];
    } else {
      $err = $csv->error_input;
      print "Failed to parse line: $err";
    }
    next;
  }
}

main;
 
