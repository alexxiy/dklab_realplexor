//@
//@ Dklab Realplexor: Comet server which handles 1000000+ parallel browser connections
//@ Author: Dmitry Koterov, dkLab (C)
//@ License: GPL 2.0
//@
//@ 2025-* Contributor: Alexxiy
//@ GitHub: http://github.com/alexxiy/
//@
//@ ATTENTION: Java-style C++ programming below. :-)
//@
//@ This is a line-by-line C++ rewrite of Perl prototype code with obvious speed
//@ optimizations (like avoiding excess copies, config pre-parsing etc.).
//@
//@ The code is so compact (2600 lines) and so simple, that I decided not to
//@ split it into *.hpp & *.cpp files nor create Makefiles, but place
//@ everything into included *.h files (like Perl, Java, C# and most of other
//@ languages do). It is not quite common for C++, but it surely simple
//@ when a program is small (especially when it is rewritten line by line
//@ from another language).
//@
//@ Also the code has global variables within the top namespace: one variable
//@ per Storage and one CONFIG, they are like singletons.
//@

#ifndef REALPLEXOR_UTILS_H
#define REALPLEXOR_UTILS_H

string SELF;
vector<string> ARGV;

void init_argv(char** argv)
{
    SELF = system_complete(path(argv[0])).string();
    ARGV.clear();
    for (int i = 1; argv[i]; i++) {
        ARGV.push_back(argv[i]);
    }
}

string extract_option(string opt)
{
    auto old = ARGV;
    ARGV.clear();
    string val;
    for (auto i = old.begin(); i != old.end(); ++i) {
        if (*i == opt) {
            ++i;
            if (i != old.end()) {
                val = *i;
            }
        } else {
            ARGV.push_back(*i);
        }
    }
    return val;
}

string get_root_dir()
{
    return path(SELF).parent_path().string();
}

const std::string strerrno()
{
    return std::strerror(errno);
}

void die(string s)
{
    s = regex_replace(s, regex("\\$!"), [](smatch s) { return strerrno(); });
    throw std::runtime_error(s);
}

std::string backtick(const std::string& cmd)
{
    FILE* f = popen(cmd.c_str(), "r");
    if (!f) {
        die(std::format("popen({}): $!", cmd));
    }
    std::string str;
    char c;
    while (EOF != (c = fgetc(f))) {
        str += c;
    }
    pclose(f);
    return str;
}

string strftime(string fmt, ptime now)
{
  auto facet = new time_facet(fmt.c_str());
  std::basic_stringstream<char> ss;
  ss.imbue(std::locale(std::cout.getloc(), facet));
  ss << now;
  return ss.str();
}

string strftime_std(ptime now)
{
    return strftime("%a %b %e %H:%M:%S %Y", now);
}

bool is_file(const string& filename)
{
    ifstream in;
    in.open(filename.c_str());
    if (in.fail()) {
        return false;
    }
    in.close();
    return true;
}

string read_file(string fname)
{
    ifstream f(fname.c_str());
    if (!f) die("Cannot open " + fname + ": $!\n");
    string content;
    while (f) {
        char buf[1024];
        f.getline(buf, sizeof(buf) - 1);
        buf[sizeof(buf) - 1] = 0;
        content += string(buf) + "\n";
    }
    content = regex_replace(content, regex("\r"), "");
    return content;
}

void strip_comments(string& line)
{
    size_t p = line.find("#");
    if (p != string::npos) {
        line.erase(p, string::npos);
    }
    trim(line);
}

bool get_http_body(const string& data, size_t& pos)
{
    size_t d = 2;
    size_t p = data.find("\n\n");
    if (p == data.npos) {
        d = 3;
        p = data.find("\n\r\n");
    }
    if (p != data.npos) {
        pos = p + d;
        return true;
    }
    return false;
}

#endif
