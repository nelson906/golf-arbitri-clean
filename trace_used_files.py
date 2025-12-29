#!/usr/bin/env python3
import os
import re
import json
import subprocess
from pathlib import Path
from collections import defaultdict

def shell_quote(s: str) -> str:
    return "'" + s.replace("'", "'\"'\"'") + "'"

class LaravelFileTracer:
    def __init__(self, project_root):
        self.project_root = Path(project_root)
        self.used_files = set()
        self.all_files = set()
        self.file_references = defaultdict(set)
        
    def trace_files(self):
        print("Analisi dei file utilizzati in corso...")
        
        # 1. Analizza routes
        self.analyze_routes()
        
        # 2. Analizza controllers
        self.analyze_controllers()
        
        # 3. Analizza views
        self.analyze_views()
        
        # 4. Analizza assets
        self.analyze_assets()
        
        # 5. Trova tutti i file del progetto
        self.find_all_files()
        
        # 6. Identifica i file non utilizzati
        unused_files = self.all_files - self.used_files
        
        return unused_files
    
    def analyze_routes(self):
        """Analizza i file di route"""
        routes_path = self.project_root / "routes"
        if routes_path.exists():
            for route_file in routes_path.glob("*.php"):
                self.used_files.add(str(route_file.relative_to(self.project_root)))
                self.parse_php_file(route_file)
    
    def analyze_controllers(self):
        """Analizza tutti i controller referenziati"""
        controllers_path = self.project_root / "app" / "Http" / "Controllers"
        if controllers_path.exists():
            for controller in controllers_path.rglob("*.php"):
                relative_path = str(controller.relative_to(self.project_root))
                if relative_path in self.file_references or self.is_referenced(controller.stem):
                    self.used_files.add(relative_path)
                    self.parse_php_file(controller)
    
    def analyze_views(self):
        """Analizza tutte le view Blade"""
        views_path = self.project_root / "resources" / "views"
        if views_path.exists():
            for view in views_path.rglob("*.blade.php"):
                relative_path = str(view.relative_to(self.project_root))
                view_name = self.blade_path_to_name(view)
                if relative_path in self.file_references or self.is_view_referenced(view_name):
                    self.used_files.add(relative_path)
                    self.parse_blade_file(view)
    
    def analyze_assets(self):
        """Analizza gli asset pubblici"""
        public_path = self.project_root / "public"
        if public_path.exists():
            # Aggiungi file statici importanti
            for important_file in ["index.php", ".htaccess", "robots.txt", "favicon.ico"]:
                file_path = public_path / important_file
                if file_path.exists():
                    self.used_files.add(str(file_path.relative_to(self.project_root)))
    
    def parse_php_file(self, file_path):
        """Analizza un file PHP per trovare riferimenti"""
        try:
            with open(file_path, 'r', encoding='utf-8') as f:
                content = f.read()
                
            # Trova use statements
            uses = re.findall(r'use\s+([A-Za-z0-9\\]+);', content)
            for use in uses:
                class_path = use.replace('\\', '/') + '.php'
                if 'App\\' in use:
                    class_path = class_path.replace('App/', 'app/')
                    self.file_references[class_path].add(str(file_path.relative_to(self.project_root)))
                    self.used_files.add(class_path)
            
            # Trova view references
            views = re.findall(r"view\s*\([\s'\"]*([^'\"\)]+)[\s'\"]*", content)
            for view in views:
                view_path = f"resources/views/{view.replace('.', '/')}.blade.php"
                self.file_references[view_path].add(str(file_path.relative_to(self.project_root)))
                self.used_files.add(view_path)
            
            # Trova Route:: references
            routes = re.findall(r"Route::[a-z]+\([\s'\"]*([^'\"]+)['\"],\s*\[?([A-Za-z0-9\\\\]+Controller)", content)
            for route, controller in routes:
                controller_path = f"app/Http/Controllers/{controller.replace('\\\\', '/')}.php"
                self.file_references[controller_path].add(str(file_path.relative_to(self.project_root)))
                self.used_files.add(controller_path)
            
            # Trova include/require
            includes = re.findall(r"(?:include|require)(?:_once)?\s*[\(]?['\"]([^'\"]+)['\"]", content)
            for inc in includes:
                self.file_references[inc].add(str(file_path.relative_to(self.project_root)))
                self.used_files.add(inc)
                
        except Exception as e:
            print(f"Errore analizzando {file_path}: {e}")
    
    def parse_blade_file(self, file_path):
        """Analizza un file Blade per trovare riferimenti"""
        try:
            with open(file_path, 'r', encoding='utf-8') as f:
                content = f.read()
                
            # Trova @include, @extends, @component, @yield, @section
            blade_directives = re.findall(r"@(?:include|extends|component|yield|section)\s*\([\s'\"]*([^'\"\)]+)[\s'\"]*", content)
            for inc in blade_directives:
                if '.' in inc:  # È un riferimento a una view
                    view_path = f"resources/views/{inc.replace('.', '/')}.blade.php"
                    self.file_references[view_path].add(str(file_path.relative_to(self.project_root)))
                    self.used_files.add(view_path)
            
            # Trova x-component references
            x_components = re.findall(r"<x-([a-z0-9.-]+)", content)
            for component in x_components:
                component_path = f"resources/views/components/{component.replace('.', '/')}.blade.php"
                self.file_references[component_path].add(str(file_path.relative_to(self.project_root)))
                self.used_files.add(component_path)
            
            # Trova asset references
            assets = re.findall(r"(?:href|src)=['\"]/?([^'\"]+\.(?:css|js|png|jpg|jpeg|gif|svg|ico|webp|pdf|txt))['\"]", content)
            for asset in assets:
                if not asset.startswith('http') and not asset.startswith('//'):
                    asset_path = f"public/{asset.lstrip('/')}"
                    self.file_references[asset_path].add(str(file_path.relative_to(self.project_root)))
                    self.used_files.add(asset_path)
            
            # Trova riferimenti Vite (@vite)
            vite_array_refs = re.findall(r"@vite\(\s*\[([^\]]+)\]\s*\)", content)
            for ref in vite_array_refs:
                asset_refs = re.findall(r"['\"]([^'\"]+)['\"]", ref)
                for asset in asset_refs:
                    self.used_files.add(asset)
            vite_single_refs = re.findall(r"@vite\(\s*['\"]([^'\"]+)['\"]\s*\)", content)
            for asset in vite_single_refs:
                self.used_files.add(asset)
            
            # Trova form actions e links (utile per mappare routes)
            actions = re.findall(r"(?:action|href)=['\"]/?([^'\"]+)['\"]", content)
            for action in actions:
                if not action.startswith('http') and not action.startswith('#'):
                    # Potrebbe essere una route
                    self.file_references[action].add(str(file_path.relative_to(self.project_root)))
                    
        except Exception as e:
            print(f"Errore analizzando {file_path}: {e}")
    
    def blade_path_to_name(self, blade_path):
        """Converte un path blade in nome view"""
        views_path = self.project_root / "resources" / "views"
        relative = blade_path.relative_to(views_path)
        name = str(relative).replace('.blade.php', '').replace('/', '.')
        return name
    
    def is_referenced(self, name):
        """Controlla se un nome è referenziato"""
        for refs in self.file_references.values():
            for ref in refs:
                if name in ref:
                    return True
        return False
    
    def is_view_referenced(self, view_name):
        """Controlla se una view è referenziata"""
        view_path = f"resources/views/{view_name.replace('.', '/')}.blade.php"
        return view_path in self.file_references or self.is_referenced(view_name)
    
    def find_all_files(self):
        """Trova tutti i file del progetto che hanno senso per l'analisi (views e asset)."""
        exclude_prefixes = [
            'vendor/', 'node_modules/', 'storage/', 'bootstrap/cache/', '.git/', '.idea/', '.vscode/',
            'public/build/', 'public/storage/'
        ]
        include_exts = ('.blade.php', '.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico', '.webp', '.pdf', '.txt')

        for root, dirs, files in os.walk(self.project_root):
            # Pruna directory escludendo per prefisso path relativo
            rel_root = os.path.relpath(root, self.project_root)
            if rel_root == '.':
                rel_root = ''
            normalized_root = rel_root + ('/' if rel_root else '')
            if any(normalized_root.startswith(prefix) for prefix in exclude_prefixes):
                dirs[:] = []
                continue

            for file in files:
                file_path = Path(root) / file
                relative_path = str(file_path.relative_to(self.project_root))
                if any(relative_path.startswith(prefix) for prefix in exclude_prefixes):
                    continue

                # Considera solo blade e asset frontend
                if relative_path.endswith(include_exts):
                    # Limita i .php ai soli blade
                    if relative_path.endswith('.php') and not relative_path.endswith('.blade.php'):
                        continue
                    self.all_files.add(relative_path)
    
    def get_tracked_files(self):
        """Restituisce l'insieme dei file tracciati da git (relative paths)."""
        try:
            out = subprocess.check_output(['git', '-C', str(self.project_root), 'ls-files'], text=True)
            return set(line.strip() for line in out.splitlines() if line.strip())
        except Exception:
            return set()

    def generate_removal_commands(self, unused_files):
        """Genera comandi per rimuovere i file non utilizzati, usando git rm se tracciati."""
        commands = []
        tracked = self.get_tracked_files()
        for file in sorted(unused_files):
            if file in tracked:
                commands.append(f"git rm -f {shell_quote(file)}")
            else:
                commands.append(f"rm {shell_quote(file)}")
        return commands


if __name__ == "__main__":
    tracer = LaravelFileTracer("/Users/iMac/Sites/golf-arbitri-clean")
    unused_files = tracer.trace_files()
    
    print(f"\\nTrovati {len(unused_files)} file non utilizzati:")
    print("-" * 80)
    
    # Raggruppa per tipo
    by_type = defaultdict(list)
    for file in unused_files:
        ext = Path(file).suffix
        by_type[ext].append(file)
    
    for ext, files in sorted(by_type.items()):
        print(f"\\n{ext} files ({len(files)}):")
        for file in sorted(files):
            print(f"  {file}")
    
    # Genera script di rimozione
    print("\\n" + "=" * 80)
    print("COMANDI PER RIMUOVERE I FILE NON UTILIZZATI:")
    print("=" * 80)
    
    commands = tracer.generate_removal_commands(unused_files)
    
    # Salva comandi in un file
    with open("remove_unused_files.sh", "w") as f:
        f.write("#!/bin/bash\\n")
        f.write("# Script per rimuovere i file non utilizzati\\n")
        f.write("# ATTENZIONE: Verificare prima di eseguire!\\n\\n")
        
        f.write("echo 'Rimozione di {} file non utilizzati...'\\n\\n".format(len(commands)))
        
        for cmd in commands:
            f.write(f"{cmd}\\n")
        
        f.write("\\necho 'Rimozione completata!'\\n")
    
    print("\\nComandi salvati in: remove_unused_files.sh")
    print("Per eseguire: chmod +x remove_unused_files.sh && ./remove_unused_files.sh")