"use client";

import React from "react";

export function IntegrationsLoader() {
  return (
    <div className="flex flex-col items-center justify-center min-h-[400px] space-y-4">
      <div className="relative w-20 h-20">
        {/* Outer pulse circle */}
        <div className="absolute inset-0 rounded-full bg-primary/20 animate-ping" />
        
        {/* Main spinning medical cross / icon container */}
        <div className="absolute inset-0 flex items-center justify-center bg-card border-2 border-primary/30 rounded-full shadow-lg animate-spin-slow">
          <svg
            className="w-10 h-10 text-primary"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={2}
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              d="M12 4v16m8-8H4"
            />
          </svg>
        </div>

        {/* Small orbiting dots */}
        <div className="absolute -inset-2 border-2 border-dashed border-primary/20 rounded-full animate-spin" style={{ animationDuration: '3s' }} />
      </div>
      
      <div className="flex flex-col items-center space-y-1">
        <h3 className="text-lg font-semibold text-primary animate-pulse">Loading Integrations</h3>
        <p className="text-sm text-muted-foreground">Connecting to DocuSign & Google Vision...</p>
      </div>

      <style jsx>{`
        @keyframes spin-slow {
          from { transform: rotate(0deg); }
          to { transform: rotate(360deg); }
        }
        .animate-spin-slow {
          animation: spin-slow 4s linear infinite;
        }
      `}</style>
    </div>
  );
}
