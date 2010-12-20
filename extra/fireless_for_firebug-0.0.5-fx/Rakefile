require 'fileutils'

desc "Package FireLess as an XPI."
task :package do
  version = File.read("VERSION").strip

  puts "Packaging FireLess #{version}"

  FileUtils.mkdir_p('pkg')
  FileUtils.rm_rf('pkg/fireless-#{version}.xpi')
  sh %{zip -r pkg/fireless-#{version}.xpi . -x@.zipignore}
end

namespace :version do
  namespace :bump do
    def bump_version(n)
      old_version = File.read("VERSION").strip.split('.').map {|m| m.to_i}
      version = old_version.dup
      version[n] += 1
      (n+1...version.size).each {|m| version[m] = 0}
      version_string = version.join('.')
      puts "Bumpting VERSION to #{version_string}"

      File.open("VERSION", "w") {|f| f.puts(version_string)}
      sh %{sed --in-place 's/version="#{old_version.join("\\.")}"/version\\="#{version.join(".")}"/' install.rdf}
      puts "Wrote VERSION #{version_string}"

      sh %{git commit --all --message="Bump VERSION to #{version_string}"}
      sh %{git tag --force #{version_string}}
    end

    desc "Increment the major version."
    task(:major) {bump_version 0}
    desc "Increment the minor version."
    task(:minor) {bump_version 1}
    desc "Increment the patch version."
    task(:patch) {bump_version 2}
  end
end
