module.exports = function (grunt) {

	require('load-grunt-tasks')(grunt);

	// Project configuration.
	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),

		composerBin: 'vendor/bin',

		shell: {
			phpcs: {
				options: {
					stdout: true
				},
				command: '<%= composerBin %>/phpcs'
			},

			phpcbf: {
				options: {
					stdout: true
				},
				command: '<%= composerBin %>/phpcbf'
			},

			phpstan: {
				options: {
					stdout: true
				},
				command: '<%= composerBin %>/phpstan analyze .'
			},

			phpunit: {
				options: {
					stdout: true
				},
				command: '<%= composerBin %>/phpunit'
			},
		},

		gitinfo: {
			commands: {
				'local.tag.current.name': ['name-rev', '--tags', '--name-only', 'HEAD'],
				'local.tag.current.nameLong': ['describe', '--tags', '--long']
			}
		},

		clean: {
			main: ['dist'] //Clean up build folder
		},

		copy: {
			// Copy the plugin to a versioned release directory
			main: {
				src: [
					'**',
					'!*.xml', '!*.log', //any config/log files
					'!node_modules/**', '!Gruntfile.js', '!package.json', '!package-lock.json', //npm/Grunt
					'!.wordpress-org/**', //wp-org assets
					'!dist/**', //build directory
					'!.git/**', //version control
					'!.github/**', //GitHub platform files
					'!tests/**', '!scripts/**', '!phpunit.xml', '!phpunit.xml.dist', //unit testing
					'!vendor/**', '!composer.lock', '!composer.phar', '!composer.json', //composer
					'!wordpress/**',
					'!.*', '!**/*~', //hidden files
					'!CONTRIBUTING.md',
					'!README.md',
					'!HOWTO.md',
					'!phpcs.xml', '!phpcs.xml.dist', '!phpstan.neon.dist', '!grumphp.yml.dist', // CodeSniffer Configuration.
					'!docker-compose.override.yml', // Local Docker Development configuration.
					'!codecov.yml', // Code coverage configuration.
					'!tools/**', // Local Development/Build tools configuration.
				],
				dest: 'dist/',
				options: {
					processContentExclude: ['**/*.{png,gif,jpg,ico,mo}'],
				},
			}
		},

		addtextdomain: {
			options: {
				textdomain: 'hello-login',    // Project text domain.
			},
			update_all_domains: {
				options: {
					updateDomains: true
				},
				src: ['*.php', '**/*.php', '!node_modules/**', '!tests/**', '!scripts/**', '!vendor/**', '!wordpress/**']
			},
		},

		wp_readme_to_markdown: {
			dest: {
				files: {
					'README.md': 'readme.txt'
				}
			},
		},

		makepot: {
			target: {
				options: {
					domainPath: '/languages',         // Where to save the POT file.
					exclude: [
						'node_modules/.*',				//npm
						'.wordpress-org/.*', 			//wp-org assets
						'dist/.*', 								//build directory
						'.git/.*', 								//version control
						'.github/.*',							//GitHub platform
						'tests/.*', 'scripts/.*',	//unit testing
						'vendor/.*', 							//composer
						'wordpress/.*',
					],                                // List of files or directories to ignore.
					mainFile: 'hello-login.php',                     // Main project file.
					potFilename: 'hello-login.pot',                  // Name of the POT file.
					potHeaders: {
						poedit: true,                   // Includes common Poedit headers.
						'report-msgid-bugs-to': 'https://github.com/hellocoop/wordpress/issues',
						'x-poedit-keywordslist': true   // Include a list of all possible gettext functions.
					},                                // Headers to add to the generated POT file.
					type: 'wp-plugin',                // Type of project (wp-plugin or wp-theme).
					updateTimestamp: true,            // Whether the POT-Creation-Date should be updated without other changes.
					updatePoFiles: true               // Whether to update PO files in the same directory as the POT file.
				}
			}
		},

		po2mo: {
			plugin: {
				src: 'languages/*.po',
				expand: true
			}
		},

		checkrepo: {
			deploy: {
				tagged: true, // Check that the last commit (HEAD) is tagged
				tag: {
					eq: '<%= pkg.version %>' // Check if highest repo tag is equal to pkg.version
				}
			}
		},

		checktextdomain: {
			options: {
				text_domain: 'hello-login',
				keywords: [
					'__:1,2d',
					'_e:1,2d',
					'_x:1,2c,3d',
					'esc_html__:1,2d',
					'esc_html_e:1,2d',
					'esc_html_x:1,2c,3d',
					'esc_attr__:1,2d',
					'esc_attr_e:1,2d',
					'esc_attr_x:1,2c,3d',
					'_ex:1,2c,3d',
					'_x:1,2c,3d',
					'_n:1,2,4d',
					'_nx:1,2,4c,5d',
					'_n_noop:1,2,3d',
					'_nx_noop:1,2,3c,4d'
				],
			},
			files: {
				src: [
					'**/*.php',
					'!node_modules/**',
					'!dist/**',
					'!tests/**',
					'!vendor/**',
					'!wordpress/**',
					'!*~',
				],
				expand: true,
			},
		},

		// Bump version numbers
		version: {
			class: {
				options: {
					prefix: "const VERSION = '"
				},
				src: ['<%= pkg.name %>.php']
			},
			header: {
				options: {
					prefix: '\\* Version:\\s+'
				},
				src: ['<%= pkg.name %>.php']
			},
			readme: {
				options: {
					prefix: 'Stable tag:\\s+'
				},
				src: ['readme.txt']
			}
		}

	});

	grunt.registerTask('phpcs', ['shell:phpcs']);
	grunt.registerTask('phpcbf', ['shell:phpcbf']);
	grunt.registerTask('phpstan', ['shell:phpstan']);
	grunt.registerTask('phpunit', ['shell:phpunit']);
	grunt.registerTask('i18n', ['addtextdomain', 'makepot', 'po2mo']);
	grunt.registerTask('readme', ['wp_readme_to_markdown']);
	grunt.registerTask('test', ['checktextdomain', 'phpcs']);
	grunt.registerTask('build', ['gitinfo', 'test', 'i18n', 'readme']);
	grunt.registerTask('release', ['checkbranch:HEAD', 'checkrepo', 'gitinfo', 'checktextdomain', 'clean', 'copy']);

};

