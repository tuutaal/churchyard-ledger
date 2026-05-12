// SPDX-License-Identifier: AGPL-3.0-or-later
import {
  Building2,
  FileInput,
  Home,
  Landmark,
  Layers,
  Map,
  Search,
  Settings,
  Shield,
  Square,
  BookOpen,
  Users,
  UserRound,
  Vault
} from "lucide-react";

export const navigationItems = [
  { href: "/dashboard", label: "Dashboard", icon: Home },
  { href: "/cemeteries", label: "Cemeteries", icon: Landmark },
  { href: "/sections", label: "Sections", icon: Layers },
  { href: "/plots", label: "Plots", icon: Square },
  { href: "/people", label: "People", icon: UserRound },
  { href: "/interments", label: "Interments", icon: Vault },
  { href: "/owners", label: "Owners", icon: Users },
  { href: "/map", label: "Map", icon: Map },
  { href: "/search", label: "Search", icon: Search },
  { href: "/imports", label: "Imports", icon: FileInput },
  { href: "/public-site-settings", label: "Public Site", icon: Building2 },
  { href: "/admin-settings", label: "Admin", icon: Shield },
  { href: "/tutorial", label: "Tutorial", icon: BookOpen },
  { href: "/setup", label: "Setup", icon: Settings }
];
