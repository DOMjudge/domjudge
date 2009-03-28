/*
   Checktestdata -- check testdata according to specification.
   Copyright (C) 2008 Jan Kuipers
   Copyright (C) 2009 Jaap Eldering (eldering@a-eskwadraat.nl).

   $Id$

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2, or (at your option)
   any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software Foundation,
   Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

 */

using namespace std;

#include <iostream>
#include <fstream>
#include <vector>
#include <string>
#include <map>
#include <boost/regex.hpp>

const int DISPLAYONERROR = 50;

int prognr, datanr,linenr, charnr;
string data;
vector<string> prog;
vector<vector<string> > parsedprog;
map<string,string> values;

void readprogram (char *filename) {

  FILE *in = fopen(filename,"rt");

  if (in == NULL) {
    cout <<  "error opening " << filename << endl;
    exit(1);
  }

  while (!feof(in)) {
    string cmd;
    char c=0;
    bool withinquotes=false;

    while (!feof(in) && (withinquotes || !isspace(c))) {
      c = fgetc(in);

      if (c!=EOF) {
        if (withinquotes || !isspace(c)) cmd += c;
        if (c=='"') withinquotes = !withinquotes;
        if (c=='\\') {
          c = fgetc(in);
          cmd += c;
          c = '\\';
        }
      }
    }

    if (cmd!="") prog.push_back(cmd);
  }

  prog.push_back("eof");
  
  fclose (in);
}

void readtestdata (char *filename) {

  FILE *in = fopen(filename,"rt");

  if (in == NULL) {
    cout <<  "error opening " << filename << endl;
    exit(1);
  }

  data = "";

  while (!feof(in)) {
    char c = fgetc(in);
    if (c != EOF) data+=c;
  }

  fclose(in);
}

void error () {
  
  int to = datanr; while (to>data.size()) to--;
  int fr = max(0,to-DISPLAYONERROR);
  
  cout << data.substr(fr,to-fr) << endl;
  cout << string(charnr,' ') << "^" << endl << endl;
  
  cout << "ERROR: line " << linenr << " character " << charnr;
  cout << " of testdata doesn't match " << prog[prognr] << endl << endl;

  exit(1);
}

vector<string> parsecommand (string cmd) {

  string parseerror = "ERROR: can't parse " + cmd + "\n";
  
  vector<string> res(1,"");

  int i=0;
  while (i<cmd.size() && isalpha(cmd[i])) res.back() += cmd[i++];
  if (i == cmd.size()) return res;

  if (cmd[i]!='(' || cmd[cmd.size()-1]!=')') {
    cout << parseerror;
    exit(1);
  }

  bool withinquotes=false;

  while (withinquotes || cmd[i]!=')') {
    if (!withinquotes && (cmd[i]=='(' || cmd[i]==','))
      res.push_back("");
    else if (withinquotes && cmd[i]=='\\') {
      i++;
      res.back() += cmd[i];
    }
    else if (cmd[i]=='"') {
      if (!withinquotes)
        if (res.back().size()>0) {
          cout<<parseerror;
          exit(1);
        }
        else 
          withinquotes=true;
      else 
        if (cmd[i+1]!=')' && cmd[i+1]!=',') {
          cout<<parseerror;
          exit(1);
        }
        else
          withinquotes=false;
    }
    else
      res.back() += cmd[i];
    
    i++;
  }

  return res;      
}

bool my_xor(bool a, bool b) { return (a && !b) || (!a && b); }

bool smaller (string a, string b) {

  int signa, signb, sign, fr;

  fr=0;
  signa = 1;
  if      ( a[0]=='+' ) { signa =  1; fr++; }
  else if ( a[0]=='-' ) { signa = -1; fr++; }
  
  while (fr<a.size() && a[fr]=='0') fr++;
  a=a.substr(fr);
  if ( a.size()==0 ) signa = 0;

  fr=0;
  signb = 1;
  if      ( b[0]=='+' ) { signb =  1; fr++; }
  else if ( b[0]=='-' ) { signb = -1; fr++; }
  while (fr<b.size() && b[fr]=='0') fr++;
  b=b.substr(fr);
  if ( b.size()==0 ) signb = 0;

  if ( signa!=signb ) return signa<signb;
  sign = signa; // == signb;
  
  if ( sign==0 ) return false;
  if ( a.size()!=b.size() ) {
      return my_xor(a.size() < b.size(),sign < 0);
  } else {
      return my_xor(a<b,sign < 0);
  }
}

string value (string x) {
  
  if (values.count(x)) return values[x];

  for (int i=0; i<x.size(); i++)
    if (!isdigit(x[i])) error();
  
  return x;
}

void checktoken (vector<string> cmd) {

  if (cmd[0]=="space") {
    if (datanr>=data.size() || data[datanr++]!=' ') error();
    charnr++;
  }
    
  else if (cmd[0]=="newline") {
    if (datanr>=data.size() || data[datanr++]!='\n') error();
    linenr++;
    charnr=0;
  }

  else if (cmd[0]=="int") {
    // Accepts format (+|-)?[0-9]+ without leading zero's
    string num;
    while (datanr<data.size() &&
           (isdigit(data[datanr]) ||
            (num.size()==0 && (data[datanr]=='-' || data[datanr]=='+')))) {
      num += data[datanr++];
      charnr++;
    }

    if (num.size()>=2 && num[0]=='0') error();
      
    if (cmd.size()>=2 && smaller(num,value(cmd[1]))) error();
    if (cmd.size()>=3 && smaller(value(cmd[2]),num)) error();
    if (cmd.size()>=4) values[cmd[3]] = num;
  }

  else if (cmd[0]=="string") {
    for (int i=0; i<cmd[1].size(); i++) {
      if (datanr>=data.size() || data[datanr++] != cmd[1][i]) error();
      charnr++;
      if (cmd[1][i]=='\n') linenr++, charnr=0;
    }
  }
    
  else if (cmd[0]=="regex") {
    boost::regex regexstr(cmd[1]);
    boost::match_results<string::const_iterator> res;
    boost::match_flag_type flags = boost::match_default | boost::match_continuous;

    if ( !boost::regex_search((string::const_iterator)&data[datanr],
                              (string::const_iterator)data.end(),
                               res,regexstr,flags) ) {
      error();
    } else {
      for (; datanr<(res[0].second-data.begin()); datanr++) {
        charnr++;
        if ( data[datanr]=='\n') linenr++, charnr=0;
      }
    }
  }

  else {
    error();
  }
}

void checktestdata () {

  while (true) {
    vector<string> cmd = parsedprog[prognr];

    if (cmd[0]=="eof") {
      if (datanr++ != data.size()) error();
      return;
    }

    else if (cmd[0]=="rep") {
      int times = atoi(value(cmd[1]).c_str());

      if (times==0) {
        int countrep=0;
        do {
          vector<string> cmd = parsedprog[prognr++];
          if (cmd[0]=="rep") countrep++;
          if (cmd[0]=="end") countrep--;
        }
        while (countrep);
      }
      else {
        int loopstart = prognr+1;
        vector<string> sep;
        if (cmd.size()>=3) sep=vector<string>(cmd.begin()+2,cmd.end());
        
        while (times--) {
          prognr = loopstart;
          checktestdata();
          if (times && sep.size()) checktoken(sep);
        }
      }
    }

    else if (cmd[0]=="end") {
      prognr++;
      return;
    }        

    else {
      checktoken(cmd);
      prognr++;
    }
  }
}
  
int main (int argc, char **argv) {

  if (argc!=3) {
    printf ("usage: %s <testdata.dat> <testdata.in>\n", argv[0]);
    return 1;
  }

  readprogram(argv[1]);
  readtestdata(argv[2]);

  parsedprog = vector<vector<string> >(prog.size());
  for (int i=0; i<prog.size(); i++)
    parsedprog[i] = parsecommand(prog[i]);
  
  linenr = charnr = 0;
  datanr = prognr = 0;

  checktestdata();

  cout << "testdata ok!" << endl;
  
  return 0;
}
